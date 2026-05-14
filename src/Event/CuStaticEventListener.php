<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Event;

use BaserCore\Event\BcModelEventListener;
use BaserCore\Utility\BcUtil;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * CuStaticEventListener
 *
 * 差分出力用キュー（cu_static_contents）を更新する model イベントリスナー。
 *
 * baserCMS5 には afterChangeStatus / afterMove イベントが存在しないため、
 * 追加・変更・公開/非公開切替・フォルダ移動はいずれも Contents.afterSave で捕捉する。
 * また Contents はソフトデリートのため通常削除では beforeDelete が発火しない。
 * 削除（ゴミ箱行き）は ContentsService::delete() が url を空にして save する挙動を利用し、
 * afterSave 内で「url が空へ変更された」ことを検知して旧URL（getOriginal）を delete キューに積む。
 *
 * action は update（再生成）/ delete（静的ファイル削除）。update は出力時に実公開状態を
 * 再評価し、公開中=生成 / 未来公開=保留 / 非公開・期限切れ=ファイル削除 に振り分ける。
 */
class CuStaticEventListener extends BcModelEventListener
{

    /**
     * 登録イベント
     *
     * @var string[]
     */
    public $events = [
        'BaserCore.Contents.afterSave',
        // ハードデリート（ゴミ箱を空にする・エイリアス削除等）時の補助
        'BaserCore.Contents.beforeDelete',
        'BaserCore.Pages.afterSave',
        'BcBlog.BlogPosts.afterSave',
        'BcBlog.BlogPosts.beforeDelete',
        'BcCustomContent.CustomEntries.afterSave',
        'BcCustomContent.CustomEntries.beforeDelete',
    ];

    public function baserCoreContentsAfterSave(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $content = $event->getData('entity');
        if (!$content || empty($content->id)) {
            return;
        }

        // 削除（ゴミ箱）: ContentsService::delete() が url を空にして save する
        if ($this->isDeletedSave($content)) {
            $oldUrl = (string) $content->getOriginal('url');
            if ($oldUrl !== '') {
                $this->enqueueDeleteByContent($content, $oldUrl);
            }
            return;
        }

        // 追加・変更・公開切替・移動: update でキュー（出力時に実公開状態を再評価）
        $this->enqueueContent($content, true, 'update');
    }

    public function baserCoreContentsBeforeDelete(Event $event): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $content = $this->fetchContentEntity($event->getData('entity'));
        if ($content && !empty($content->url)) {
            $this->enqueueDeleteByContent($content, (string) $content->url);
        }
        return true;
    }

    public function baserCorePagesAfterSave(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $page = $event->getData('entity');
        if (!$page || empty($page->id)) {
            return;
        }

        $Contents = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        $content = $Contents->find()
            ->where([
                'plugin' => 'BaserCore',
                'type' => 'Page',
                'entity_id' => $page->id,
            ])
            ->first();
        if (!$content) {
            return;
        }

        $this->enqueueContent($content, false, 'update');
    }

    public function bcBlogBlogPostsAfterSave(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $blogPost = $event->getData('entity');
        if (!$blogPost || empty($blogPost->id) || empty($blogPost->blog_content_id)) {
            return;
        }

        // 記事詳細は update（出力時に公開状態を再評価）。所属ブログの一覧・アーカイブも再生成。
        $this->enqueueBlogPost($blogPost, 'update');
    }

    public function bcBlogBlogPostsBeforeDelete(Event $event): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $blogPost = $event->getData('entity');
        if ($blogPost && !empty($blogPost->id) && !empty($blogPost->blog_content_id)) {
            // 記事詳細は削除、所属ブログの一覧・アーカイブは再生成
            $this->enqueueBlogPost($blogPost, 'delete');
        }

        return true;
    }

    public function bcCustomContentCustomEntriesAfterSave(Event $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $entry = $event->getData('entity');
        if (!$entry || empty($entry->id) || empty($entry->custom_table_id)) {
            return;
        }

        // 詳細は update（出力時に公開状態を再評価）。所属カスタムコンテンツの一覧も再生成。
        $this->enqueueCustomEntry($entry, 'update');
    }

    public function bcCustomContentCustomEntriesBeforeDelete(Event $event): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $entry = $event->getData('entity');
        if ($entry && !empty($entry->id) && !empty($entry->custom_table_id)) {
            // 詳細は削除、所属カスタムコンテンツの一覧は再生成
            $this->enqueueCustomEntry($entry, 'delete');
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * CRON差分キューを蓄積すべきリクエストかどうか
     */
    private function isEnabled(): bool
    {
        return BcUtil::isAdminSystem() && (bool) Configure::read('CuStatic.cronEnabled');
    }

    /**
     * 「削除（ゴミ箱行き）」の保存かどうか
     *
     * ContentsService::delete() は url を空にして save するため、
     * url が空へ変更され、かつ変更前は非空だった場合を削除とみなす。
     */
    private function isDeletedSave(object $content): bool
    {
        if (!$content->isDirty('url')) {
            return false;
        }
        if ((string) $content->url !== '') {
            return false;
        }
        return (string) $content->getOriginal('url') !== '';
    }

    private function fetchContentEntity(mixed $entity): ?object
    {
        if (!$entity || empty($entity->id)) {
            return null;
        }

        $Contents = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        $content = $Contents->find()
            ->where(['id' => $entity->id])
            ->first();
        if ($content) {
            return $content;
        }

        if (!empty($entity->entity_id) && !empty($entity->type) && !empty($entity->plugin)) {
            return $Contents->find()
                ->where([
                    'plugin' => $entity->plugin,
                    'type' => $entity->type,
                    'entity_id' => $entity->entity_id,
                ])
                ->first();
        }

        return null;
    }

    /**
     * Content を再生成/削除キューに積む
     *
     * @param object $content Content エンティティ
     * @param bool $includeChildren ContentFolder のとき配下も対象にするか
     * @param string $action update / delete
     * @return void
     */
    private function enqueueContent(object $content, bool $includeChildren, string $action): void
    {
        $enableTypes = Configure::read('CuStatic.types') ?? [];
        if (!in_array($content->type, $enableTypes, true)) {
            return;
        }

        $this->enqueue($this->buildQueueData($content, $action));

        if (!$includeChildren || $content->type !== 'ContentFolder') {
            return;
        }

        $Contents = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        $children = $Contents->find()
            ->where([
                'lft >' => $content->lft,
                'rght <' => $content->rght,
                'site_id' => $content->site_id,
            ])
            ->all();
        foreach ($children as $child) {
            $this->enqueue($this->buildQueueData($child, $action));
        }
    }

    /**
     * 削除された Content を delete キューに積む（旧URLを使用）
     */
    private function enqueueDeleteByContent(object $content, string $url): void
    {
        $enableTypes = Configure::read('CuStatic.types') ?? [];
        if (!in_array($content->type, $enableTypes, true)) {
            return;
        }
        $data = $this->buildQueueData($content, 'delete');
        $data['url'] = $url;
        $this->enqueue($data);
    }

    /**
     * ブログ記事をキューに積む（記事詳細＋所属ブログ＋連動URL）
     *
     * @param object $blogPost BlogPost エンティティ
     * @param string $detailAction 記事詳細の action（update / delete）
     * @return void
     */
    private function enqueueBlogPost(object $blogPost, string $detailAction): void
    {
        $Contents = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        $content = $Contents->find()
            ->where([
                'plugin' => 'BcBlog',
                'type' => 'BlogContent',
                'entity_id' => $blogPost->blog_content_id,
            ])
            ->first();
        if (!$content) {
            return;
        }

        // 所属ブログの一覧・アーカイブを再生成（記事の増減はカテゴリ/日付/著者/ページングに波及）
        $this->enqueue($this->buildQueueData($content, 'update'));

        // 記事詳細URLはスラッグ（name）優先、無ければ no（なければ id）。BlogPostsService::getUrl() 準拠。
        $articleSlug = !empty($blogPost->name) ? (string) $blogPost->name : (string) ($blogPost->no ?: $blogPost->id);
        $this->enqueue([
            'name' => $content->name,
            'plugin' => 'BcBlog',
            'type' => 'BlogPost',
            'action' => $detailAction,
            'content_id' => $blogPost->blog_content_id,
            'entity_id' => $blogPost->id,
            'url' => rtrim((string) $content->url, '/') . '/archives/' . $articleSlug,
            'site_id' => (int) $content->site_id,
        ]);

        // #3 連動更新URL（blog_callback_diff_{siteId}_{blogContentId}）
        $this->enqueueCallbackUrls((int) $content->site_id, (int) $blogPost->blog_content_id);
    }

    /**
     * カスタムエントリーをキューに積む（詳細＋所属カスタムコンテンツ一覧）
     *
     * エントリー → custom_table_id → CustomContents(contain Contents) で所属 Content(URL) を解決する。
     * 詳細URLは `{content->url}view/{name ?: id}`（CustomEntriesService::getUrl 準拠）。
     *
     * @param object $entry CustomEntry エンティティ
     * @param string $detailAction update / delete
     * @return void
     */
    private function enqueueCustomEntry(object $entry, string $detailAction): void
    {
        $CustomContents = TableRegistry::getTableLocator()->get('BcCustomContent.CustomContents');
        $customContent = $CustomContents->find()
            ->where(['CustomContents.custom_table_id' => $entry->custom_table_id])
            ->contain(['Contents'])
            ->first();
        if (!$customContent || empty($customContent->content)) {
            return;
        }
        $content = $customContent->content;

        // 所属カスタムコンテンツの一覧を再生成（エントリー増減はページングに波及）
        $this->enqueue($this->buildQueueData($content, 'update'));

        // 詳細URLは name(slug) 優先、無ければ id
        $slug = !empty($entry->name) ? (string) $entry->name : (string) $entry->id;
        $this->enqueue([
            'name' => $content->name,
            'plugin' => 'BcCustomContent',
            'type' => 'CustomEntry',
            'action' => $detailAction,
            // content_id は親 customContentId（BlogPost の content_id=blog_content_id に倣う）
            'content_id' => (int) $customContent->id,
            'entity_id' => (int) $entry->id,
            'url' => rtrim((string) $content->url, '/') . '/view/' . $slug,
            'site_id' => (int) $content->site_id,
        ]);
    }

    /**
     * 連動更新URL（blog_callback）を update キューに積む
     *
     * 記事更新時に一緒に最新化したいURL（例: 新着を読み込むトップページ）を
     * config の target_config から読み、各URLの Content を解決してキューする。
     */
    private function enqueueCallbackUrls(int $siteId, int $blogContentId): void
    {
        $targetConfig = $this->getTargetConfig();
        $key = 'blog_callback_diff_' . $siteId . '_' . $blogContentId;
        $raw = $targetConfig[$key] ?? '';
        if (!is_string($raw) || trim($raw) === '') {
            return;
        }

        $Contents = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $url = trim((string) $line);
            if ($url === '') {
                continue;
            }
            $content = $Contents->find()->where(['url' => $url])->first();
            if ($content) {
                $this->enqueue($this->buildQueueData($content, 'update'));
            }
        }
    }

    /**
     * cu_static_configs の target_config を連想配列で返す
     */
    private function getTargetConfig(): array
    {
        try {
            $CuStaticConfigs = TableRegistry::getTableLocator()->get('CuStatic.CuStaticConfigs');
            $config = $CuStaticConfigs->getConfig();
            if (!$config || empty($config->target_config)) {
                return [];
            }
            return json_decode((string) $config->target_config, true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function buildQueueData(object $content, string $action): array
    {
        return [
            'name' => $content->name,
            'plugin' => $content->plugin,
            'type' => $content->type,
            'action' => $action,
            'content_id' => $content->id,
            'entity_id' => $content->entity_id,
            'url' => $content->url,
            'site_id' => (int) $content->site_id,
        ];
    }

    private function enqueue(array $data): bool
    {
        try {
            $CuStaticContents = TableRegistry::getTableLocator()->get('CuStatic.CuStaticContents');
            return $CuStaticContents->enqueue($data);
        } catch (\Throwable $e) {
            Log::write('error', '[enqueue] エラー: ' . $e->getMessage(), ['scope' => [LOG_CU_STATIC]]);
            return false;
        }
    }

}
