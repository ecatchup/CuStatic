<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Service;

use Cake\Core\Configure;
use Cake\Event\EventDispatcherTrait;
use Cake\Http\Client;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use RuntimeException;

/**
 * CuStaticService
 *
 * 静的HTML出力のメインロジック。
 * HTML取得は HttpClient による同一ドメインHTTPリクエストに変更（requestAction 廃止対応）。
 * 並列処理は PCNTL fork によるワーカープール方式を採用。
 */
class CuStaticService implements CuStaticServiceInterface
{

    /**
     * イベント発火用トレイト。
     *
     * CuStatic.beforeExport / CuStatic.afterExport を発火し、アドオンプラグインが
     * 出力ライフサイクルへ割り込めるようにする。ローカルEventManagerだが、
     * グローバル（EventManager::instance()）に登録されたリスナーも合わせて呼ばれる。
     *
     * @use \Cake\Event\EventDispatcherTrait<\CuStatic\Service\CuStaticService>
     */
    use EventDispatcherTrait;

    /**
     * @var \CuStatic\Model\Table\CuStaticConfigsTable
     */
    protected $CuStaticConfigs;

    /**
     * @var \CuStatic\Model\Table\CuStaticContentsTable
     */
    protected $CuStaticContents;

    /**
     * @var \BaserCore\Model\Table\ContentsTable
     */
    protected $Contents;

    /**
     * @var \BaserCore\Model\Table\SitesTable
     */
    protected $Sites;

    /**
     * 実行中モードのログ用ラベル（全件 / 差分）。fork 前に設定し子プロセスへ引き継ぐ。
     *
     * @var string
     */
    protected string $modeLabel = 'all';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->CuStaticConfigs = TableRegistry::getTableLocator()->get('CuStatic.CuStaticConfigs');
        $this->CuStaticContents = TableRegistry::getTableLocator()->get('CuStatic.CuStaticContents');
        $this->Contents = TableRegistry::getTableLocator()->get('BaserCore.Contents');
        $this->Sites = TableRegistry::getTableLocator()->get('BaserCore.Sites');
    }

    /**
     * 全件または差分のHTML出力を実行する
     *
     * @param array $options
     * @return bool
     */
    public function export(array $options = []): bool
    {
        $options = array_merge([
            'all' => true,
            'siteIds' => null,
            'workers' => Configure::read('CuStatic.defaultWorkers') ?? 4,
        ], $options);

        $config = $this->CuStaticConfigs->getConfig();
        if (!$config) {
            throw new RuntimeException('CuStatic設定が存在しません。管理画面から設定を保存してください。');
        }

        // 二重起動防止（ロック期限つき）。実行中でも started から lockTimeout を超えていれば
        // 異常終了で取り残された stale ロックとみなして奪取する。期限内なら安全にスキップする。
        if ($config->status) {
            $lockTimeout = (int) (Configure::read('CuStatic.lockTimeout') ?? 3600);
            $startedTs = $config->started ? $config->started->getTimestamp() : 0;
            if ($startedTs && (time() - $startedTs) < $lockTimeout) {
                $this->writeLog('[export] 既に実行中のためスキップします。');
                return false;
            }
            $this->writeLog('[export] 前回ロックが古い（stale）ため奪取して再開します。');
        }

        $this->CuStaticConfigs->updateStatus($config->id, true);
        $progress = new CuStaticProgressReporter($this->CuStaticConfigs, $config->id);
        $progress->setTotal(0);

        try {
            $exportPath = rtrim($config->export_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $baseUrl = $this->getBaseUrl($config->base_url);
            $targetConfig = json_decode($config->target_config ?? '{}', true) ?? [];
            $workers = (int) $options['workers'];

            // ログ用モードラベル（全件 / 差分）。exportHtml など子プロセスのログにも付与する。
            $this->modeLabel = $options['all'] ? 'all' : 'diff';
            $deleteCount = 0;

            $this->writeLog(sprintf('[export][%s] 開始 exportPath=%s baseUrl=%s', $this->modeLabel, $exportPath, $baseUrl));

            // 対象サイトを確定
            $siteIds = $options['siteIds'] ?? $this->getActiveSiteIds();
            $diffClearIds = [];
            $diffExpireIds = [];

            if ($options['all']) {
                // 全件モード: 出力先を初期化し、アセットをコピー
                if (is_dir($exportPath)) {
                    // 破壊的削除の前に、アプリ本体・webroot 等の重要ディレクトリを指していないか検証する
                    $this->assertSafeExportPath($exportPath);
                    $this->deleteDirectory($exportPath);
                }
                if (!is_dir($exportPath)) {
                    mkdir($exportPath, 0777, true);
                }
                // 静的アセットをコピー（対象サイトのテーマ + テーマ梱包プラグイン + 設定プラグイン）
                $themes = $this->getTargetThemes($siteIds);
                $this->copyAssets($exportPath, $themes);

                $jobs = $this->buildJobs($siteIds, $targetConfig, $exportPath, $baseUrl);
            } else {
                // 差分モード: キューから再生成ジョブと削除対象を計画（アセット再コピーはしない）
                if (!is_dir($exportPath)) {
                    mkdir($exportPath, 0777, true);
                }
                $plan = $this->buildDiffPlan($siteIds, $targetConfig, $exportPath, $baseUrl);
                $planDeleteCount = count($plan['deletePaths']);
                if ($planDeleteCount) {
                    $this->writeLog(sprintf('[export][%s] 削除対象=%d 件', $this->modeLabel, $planDeleteCount));
                }
                foreach ($plan['deletePaths'] as $delPath) {
                    if ($this->deleteStaticPath($delPath, $exportPath)) {
                        $deleteCount++;
                    }
                }
                $jobs = $plan['jobs'];
                $diffClearIds = $plan['clearIds'];
                $diffExpireIds = $plan['expireIds'];
            }

            $progressMax = count($jobs);
            $progress->setTotal($progressMax);
            $this->writeLog(sprintf('[export][%s] 出力対象件数=%d', $this->modeLabel, $progressMax));

            // 出力直前フック。アドオンは前処理や進捗の事前予約（$progress->reserve()）に利用できる。
            $this->dispatchEvent('CuStatic.beforeExport', [
                'exportPath' => $exportPath,
                'mode' => $this->modeLabel,
                'siteIds' => $siteIds,
                'config' => $config,
                'progress' => $progress,
            ]);

            // PCNTL 対応チェック
            if ($workers > 1 && function_exists('pcntl_fork')) {
                $this->exportParallel($jobs, $workers, function (int $done) use ($progress) {
                    $progress->set($done);
                });
            } else {
                foreach ($jobs as $i => $job) {
                    $this->exportHtml($job['url'], $job['path'], $job['publish']);
                    $progress->set($i + 1);
                }
            }

            // 差分モードは処理済みキューのみ削除（未来公開の保留行はキューに残す）
            if (!$options['all']) {
                if (!empty($diffClearIds)) {
                    $this->CuStaticContents->deleteAll(['id IN' => $diffClearIds]);
                }
                // 生成済みで公開終了待ちの行は expire へ遷移させ、終了到来まで保持
                if (!empty($diffExpireIds)) {
                    $this->CuStaticContents->updateAll(['action' => 'expire'], ['id IN' => $diffExpireIds]);
                }
            }

            $this->writeLog(sprintf('[export][%s] 完了（出力 %d 件 / 削除 %d 件）', $this->modeLabel, $progressMax, $deleteCount));

            // 出力完了フック。アドオンは追加ファイルの書き出し等の後処理に利用できる。
            // $progress->reserve()/advance() で追加処理も進捗バーへ反映できる。
            // updateStatus(false)（完了フラグ）より前に発火するため、実行中表示が維持される。
            $this->dispatchEvent('CuStatic.afterExport', [
                'exportPath' => $exportPath,
                'mode' => $this->modeLabel,
                'siteIds' => $siteIds,
                'config' => $config,
                'progress' => $progress,
            ]);
        } catch (\Throwable $e) {
            $this->writeLog(sprintf('[export][%s] エラー: %s', $this->modeLabel, $e->getMessage()));
            $this->CuStaticConfigs->updateStatus($config->id, false);
            throw $e;
        }

        $this->CuStaticConfigs->updateStatus($config->id, false);
        return true;
    }

    /**
     * 指定URLのHTMLを取得してファイルに保存する
     *
     * @param string $url
     * @param string $path
     * @param bool $publish
     * @return void
     */
    public function exportHtml(string $url, string $path, bool $publish = true): void
    {
        $dir = dirname($path);
        // 並列実行時は複数ワーカーが同一ディレクトリを同時に作成しようとするため、
        // 競合（mkdir 失敗＝他ワーカーが先に作成）を許容する。作成後も存在しなければ失敗として扱う。
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            $this->writeLog(sprintf('[exportHtml][%s] ディレクトリ作成失敗: %s', $this->modeLabel, $dir));
            return;
        }

        if (!$publish) {
            // 非公開コンテンツは空ファイルを設置
            file_put_contents($path, '');
            $this->writeLog(sprintf('[exportHtml][%s] 非公開（空ファイル）: %s', $this->modeLabel, $path));
            return;
        }

        $maxAttempts = max(1, (int) (Configure::read('CuStatic.httpMaxAttempts') ?? 3));
        $client = new Client(['ssl_verify_peer' => false, 'ssl_verify_peer_name' => false, 'ssl_verify_host' => false]);
        $lastError = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $client->get($url);
                if ($response->isOk()) {
                    $content = $response->getBody()->getContents();
                    // HTMLファイルのみ内部リンクを書き換え（RSS等はスキップ）
                    if (pathinfo($path, PATHINFO_EXTENSION) === 'html') {
                        $content = $this->convertHtmlLinks($content, $url);
                        // 生成HTMLのフィルタフック。アドオンは <script>/<link> の注入や
                        // 追加のリンク書き換え等に利用できる。リスナーが文字列を返せば置き換わる。
                        // 並列（fork子）でも発火する（グローバル登録リスナーは fork 前に登録済み）。
                        $filtered = $this->dispatchEvent('CuStatic.filterHtml', [
                            'html' => $content,
                            'url' => $url,
                            'path' => $path,
                        ])->getResult();
                        if (is_string($filtered)) {
                            $content = $filtered;
                        }
                    }
                    file_put_contents($path, $content);
                    $this->writeLog(sprintf('[exportHtml][%s] 出力完了: %s', $this->modeLabel, $path));
                    return;
                }

                $status = $response->getStatusCode();
                // 4xx はリトライしても結果が変わらないため即時打ち切り
                if ($status < 500) {
                    $this->writeLog(sprintf('[exportHtml][%s] HTTPエラー %d（スキップ）: %s', $this->modeLabel, $status, $url));
                    return;
                }
                $lastError = 'HTTP ' . $status;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            if ($attempt < $maxAttempts) {
                usleep(300000); // 0.3秒待って再試行
            }
        }

        // リトライ上限。一時障害で誤った白紙ページを残さないよう空ファイルは作成せず、失敗としてログに残す。
        $this->writeLog(sprintf('[exportHtml][%s] 取得失敗（%d回試行, %s）: %s', $this->modeLabel, $maxAttempts, $lastError, $url));
    }

    /**
     * HTML内の内部リンク（<a href>）を静的HTML向けに書き換える
     *
     * DOMDocument を使うと saveHTML() が日本語を数値文字参照（&#xxxx;）へ変換するため、
     * href 属性のみを文字列置換で書き換え、本文は一切変更しない。
     *
     * @param string $html
     * @param string $currentUrl 取得元ページのURL（相対リンク・ページネーション解決に使用）
     * @return string
     */
    private function convertHtmlLinks(string $html, string $currentUrl): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $baseHost = parse_url($currentUrl, PHP_URL_HOST) ?? '';
        $currentPath = parse_url($currentUrl, PHP_URL_PATH) ?? '/';

        return (string) preg_replace_callback(
            '/(<a\b[^>]*?\shref\s*=\s*)(["\'])(.*?)\2/i',
            fn($m) => $m[1] . $m[2] . $this->convertHref($m[3], $baseHost, $currentPath) . $m[2],
            $html
        );
    }

    /**
     * 単一の href を静的HTML向けパスへ変換する
     *
     * 変換対象は同一ホストの内部リンクのみ。外部URL・アンカー・mailto 等はそのまま返す。
     *   - 末尾 / → /index.html
     *   - 拡張子なし → .html 付与
     *   - ページネーション ?page=N（N>=2）→ {パス}/page-N.html（baserCMS5 のクエリ形式に対応）
     *
     * @param string $href 元の href
     * @param string $baseHost 対象ホスト
     * @param string $currentPath 取得元ページのパス（相対リンク解決の基準）
     * @return string
     */
    private function convertHref(string $href, string $baseHost, string $currentPath): string
    {
        $raw = trim($href);
        if ($raw === '') {
            return $href;
        }

        // アンカー・外部スキームはそのまま
        // 区切り文字は ~ を使用（パターンに含まれるアンカー # をリテラルとして扱うため。# 区切りだと誤認して warning になる）
        if (preg_match('~^(#|mailto:|tel:|javascript:|ftp:|data:)~i', $raw)) {
            return $href;
        }

        // 属性値のエスケープを戻し（&amp; → &）、フラグメントを除去して解析
        $decoded = explode('#', str_replace('&amp;', '&', $raw), 2)[0];
        if ($decoded === '') {
            return $href;
        }

        $query = '';
        if (preg_match('#^https?://#i', $decoded) || str_starts_with($decoded, '//')) {
            // 絶対URL：別ホストはスキップ
            $p = parse_url($decoded);
            if (($p['host'] ?? '') !== $baseHost) {
                return $href;
            }
            $path = $p['path'] ?? '/';
            $query = $p['query'] ?? '';
        } elseif (str_starts_with($decoded, '?')) {
            // クエリのみ（ページネーション等）→ 現在ページのパスに対する相対
            $path = $currentPath;
            $query = substr($decoded, 1);
        } else {
            [$p, $query] = array_pad(explode('?', $decoded, 2), 2, '');
            if (str_starts_with($p, '/')) {
                $path = $p; // ルート相対
            } else {
                // その他の相対 → 現在パスのディレクトリ基準
                $path = rtrim(str_replace('\\', '/', dirname($currentPath)), '/') . '/' . $p;
            }
        }

        if ($path === '') {
            $path = '/';
        }

        // 拡張子付き（CSS/JS/画像等）はそのまま
        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return $href;
        }

        // 末尾スラッシュ → index
        if (str_ends_with($path, '/')) {
            $path .= 'index';
        }

        // 日付アーカイブの月・日をゼロ埋めに正規化する。
        // baserCMS はウィジェットにより前ゼロ有無が異なる（カレンダー: /date/2026/6・/date/2026/7/2、
        // 月別/日別アーカイブ: /date/2026/07）。出力ファイルは前ゼロ形式のため、リンク側を揃える。
        $path = (string) preg_replace_callback(
            '#(/archives/date)/(\d{4})(?:/(\d{1,2}))?(?:/(\d{1,2}))?$#',
            function ($mm) {
                $out = $mm[1] . '/' . $mm[2];
                if (($mm[3] ?? '') !== '') {
                    $out .= '/' . str_pad($mm[3], 2, '0', STR_PAD_LEFT);
                }
                if (($mm[4] ?? '') !== '') {
                    $out .= '/' . str_pad($mm[4], 2, '0', STR_PAD_LEFT);
                }
                return $out;
            },
            $path
        );

        // ページネーション ?page=N（N>=2）。page=1・クエリなしは一覧本体（index等）
        if ($query !== '' && preg_match('/(?:^|&)page=(\d+)/', $query, $m) && (int) $m[1] >= 2) {
            return rtrim($path, '/') . '/page-' . (int) $m[1] . '.html';
        }

        return $path . '.html';
    }

    /**
     * PCNTL fork によるURL群の並列出力
     *
     * fork 前にDBコネクションを閉じ、各子プロセスで再接続する。
     * 進捗は一時ファイル（完了ジョブ1件につき1ファイル）で親プロセスに集計し、
     * $onProgress コールバック経由で呼び出し元へ完了件数を通知する。
     *
     * @param array $jobs
     * @param int $workers
     * @param callable|null $onProgress 完了件数(int)を受け取る進捗コールバック
     * @return void
     */
    public function exportParallel(array $jobs, int $workers, ?callable $onProgress = null): void
    {
        $total = count($jobs);
        if ($total === 0) {
            return;
        }

        $chunks = array_chunk($jobs, (int) ceil($total / $workers));

        // 進捗集計用の一時ディレクトリ（子が完了ジョブ1件につき1ファイルを作成）
        $progressDir = null;
        if ($onProgress !== null) {
            $progressDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cu_static_progress_' . getmypid();
            if (!is_dir($progressDir)) {
                mkdir($progressDir, 0700, true);
            }
        }

        // fork 前にDBコネクションを解放（親・子それぞれで再接続し、コネクション共有を避ける）
        \Cake\Datasource\ConnectionManager::get('default')->getDriver()->disconnect();

        $pids = [];
        foreach ($chunks as $chunkIndex => $chunk) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('pcntl_fork() に失敗しました。');
            }

            if ($pid === 0) {
                // 子プロセス: DB再接続して担当チャンクを処理
                \Cake\Datasource\ConnectionManager::get('default')->getDriver()->connect();
                foreach ($chunk as $jobIndex => $job) {
                    $this->exportHtml($job['url'], $job['path'], $job['publish']);
                    if ($progressDir !== null) {
                        touch($progressDir . DIRECTORY_SEPARATOR . $chunkIndex . '_' . $jobIndex);
                    }
                }
                exit(0);
            }

            $pids[] = $pid;
        }

        // 親プロセス: 自身のDB接続を張り直す（子とは別コネクション）
        \Cake\Datasource\ConnectionManager::get('default')->getDriver()->connect();

        if ($progressDir !== null) {
            $this->waitWithProgress($pids, $progressDir, $total, $onProgress);
            $this->deleteDirectory($progressDir);
        } else {
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
            }
        }
    }

    /**
     * 子プロセスの完了を待機しながら、一時ファイル数から進捗を集計する
     *
     * @param int[] $pids 監視対象の子プロセスID
     * @param string $progressDir 進捗マーカーファイルのディレクトリ
     * @param int $total 全ジョブ件数
     * @param callable $onProgress 完了件数(int)を受け取る進捗コールバック
     * @return void
     */
    private function waitWithProgress(array $pids, string $progressDir, int $total, callable $onProgress): void
    {
        $running = array_fill_keys($pids, true);
        while (in_array(true, $running, true)) {
            foreach ($pids as $pid) {
                if (!$running[$pid]) {
                    continue;
                }
                $result = pcntl_waitpid($pid, $status, WNOHANG);
                if ($result === $pid || $result === -1) {
                    $running[$pid] = false;
                }
            }
            $onProgress(min($this->countProgressMarkers($progressDir), $total));
            if (in_array(true, $running, true)) {
                usleep(200000); // 0.2秒待機してから再ポーリング
            }
        }
        // 最終集計
        $onProgress(min($this->countProgressMarkers($progressDir), $total));
    }

    /**
     * 進捗マーカーファイル数を数える
     *
     * @param string $progressDir
     * @return int
     */
    private function countProgressMarkers(string $progressDir): int
    {
        return count(glob($progressDir . DIRECTORY_SEPARATOR . '*') ?: []);
    }

    /**
     * PID 付きでログを書き出す
     *
     * 並列（PCNTL）実行時に、どのプロセス（親/各ワーカー）の出力かを判別できるよう
     * すべてのメッセージ先頭にプロセスIDを付与する。
     *
     * @param string $message
     * @return void
     */
    private function writeLog(string $message): void
    {
        Log::write('info', sprintf('[pid:%d] %s', getmypid(), $message), ['scope' => [LOG_CU_STATIC]]);
    }

    /**
     * 静的アセットをコピーする
     *
     * baserCMS5 ではテーマもプラグインで、資産は plugins/{Theme}/webroot を
     * URL /{underscore(Theme)}/ で配信する。よってテーマ・テーマ梱包プラグイン・
     * 設定プラグインの webroot を、いずれも同一方式でコピーする。
     *
     * @param string $exportPath 出力先フォルダパス
     * @param array $themes 対象テーマ名の配列
     * @return void
     */
    public function copyAssets(string $exportPath, array $themes = []): void
    {
        $baseDir = rtrim(Configure::read('App.www_root') ?? WWW_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $rsyncCommand = Configure::read('CuStatic.rsyncCommand') ?? '';

        // webroot 直下の共通静的ファイル（アップロードファイル等）
        foreach (['css', 'js', 'img', 'files'] as $dir) {
            $src = $baseDir . $dir . DIRECTORY_SEPARATOR;
            if (!is_dir($src)) {
                continue;
            }
            $this->copyDirectory($src, $exportPath . $dir . DIRECTORY_SEPARATOR, $rsyncCommand);
            $this->writeLog('[copyAssets] ' . $src);
        }

        // テーマ本体 + テーマ梱包プラグイン + 設定プラグインの webroot をコピー
        $webrootPlugins = $themes;
        foreach ($themes as $theme) {
            $webrootPlugins = array_merge($webrootPlugins, \BaserCore\Utility\BcUtil::getThemesPlugins($theme));
        }
        $webrootPlugins = array_merge($webrootPlugins, Configure::read('CuStatic.plugins') ?? []);
        $webrootPlugins = array_values(array_unique(array_filter($webrootPlugins)));

        foreach ($webrootPlugins as $pluginName) {
            $pluginPath = \BaserCore\Utility\BcUtil::getPluginPath($pluginName);
            if (!$pluginPath || !is_dir($pluginPath . 'webroot')) {
                $this->writeLog('[copyAssets] webroot なしスキップ: ' . $pluginName);
                continue;
            }
            $src = $pluginPath . 'webroot' . DIRECTORY_SEPARATOR;
            $dst = $exportPath . \Cake\Utility\Inflector::underscore($pluginName) . DIRECTORY_SEPARATOR;
            $this->copyDirectory($src, $dst, $rsyncCommand);
            $this->writeLog('[copyAssets] plugin ' . $pluginName . ' -> ' . $dst);
        }
    }

    /**
     * ベースURLを取得する
     *
     * @param string $configBaseUrl
     * @return string
     */
    public function getBaseUrl(string $configBaseUrl = ''): string
    {
        if ($configBaseUrl !== '') {
            return rtrim($configBaseUrl, '/');
        }
        $sslUrl = Configure::read('BcEnv.sslUrl') ?? '';
        $siteUrl = Configure::read('BcEnv.siteUrl') ?? '';
        return rtrim($sslUrl ?: $siteUrl, '/');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * 有効なサイトIDの一覧を返す
     *
     * @return array
     */
    private function getActiveSiteIds(): array
    {
        $ids = $this->Sites->find()
            ->where(['status' => true])
            ->all()
            ->map(fn($s) => $s->id)
            ->toArray();
        $ids[] = 0; // メインサイト
        return $ids;
    }

    /**
     * 対象サイトに適用されているテーマ一覧を取得する（コアフロントテーマを含む）
     *
     * @param array $siteIds
     * @return array
     */
    private function getTargetThemes(array $siteIds): array
    {
        $realSiteIds = array_filter($siteIds, fn($id) => (int) $id > 0);
        $themes = [];
        if ($realSiteIds) {
            $themes = $this->Sites->find()
                ->where(['id IN' => $realSiteIds])
                ->all()
                ->map(fn($s) => $s->theme)
                ->toList();
        }

        $coreFrontTheme = Configure::read('BcApp.coreFrontTheme');
        if ($coreFrontTheme) {
            $themes[] = $coreFrontTheme;
        }

        return array_values(array_unique(array_filter($themes)));
    }

    /**
     * 全件モードの出力ジョブリストを構築する
     *
     * @param array $siteIds 対象サイトIDリスト
     * @param array $targetConfig 出力対象設定
     * @param string $exportPath 出力先パス
     * @param string $baseUrl 出力時に使うベースURL（設定 base_url を反映済み）
     * @return array [['url' => string, 'path' => string, 'publish' => bool], ...]
     */
    private function buildJobs(array $siteIds, array $targetConfig, string $exportPath, string $baseUrl): array
    {
        $jobs = [];
        $enableTypes = Configure::read('CuStatic.types') ?? [];
        $modePrefix = 'main_';

        $contents = $this->Contents->find()
            ->where([
                'site_id IN' => $siteIds,
                'type IN' => $enableTypes,
                'status' => true,
            ])
            ->orderBy(['site_id' => 'ASC', 'lft' => 'ASC'])
            ->all();

        foreach ($contents as $content) {
            $siteId = $content->site_id;
            $pageUrl = ltrim($content->url, '/');
            $pagePath = str_replace('/', DIRECTORY_SEPARATOR, $pageUrl);
            $prefix = '_' . $siteId;

            switch ($content->type) {
                case 'ContentFolder':
                    // ルートフォルダ(/)と /index ページは同一の index.html に解決されるため、
                    // addJob() でパスをキーに重複排除する。
                    if ($targetConfig[$modePrefix . 'folder' . $prefix] ?? true) {
                        $this->addJob($jobs, $baseUrl . '/' . $pageUrl, $exportPath . $pagePath . 'index.html');
                    }
                    break;
                case 'Page':
                    if ($targetConfig[$modePrefix . 'page' . $prefix] ?? true) {
                        $this->addJob($jobs, $baseUrl . '/' . $pageUrl, $exportPath . rtrim($pagePath, '/') . '.html');
                    }
                    break;
                case 'BlogContent':
                    $blogJobs = $this->buildBlogJobs($content, $siteId, $targetConfig, $exportPath, $baseUrl, $pageUrl, $pagePath, $modePrefix);
                    $jobs = array_merge($jobs, $blogJobs);
                    break;
                case 'CustomContent':
                    $customJobs = $this->buildCustomContentJobs($content, $siteId, $targetConfig, $exportPath, $baseUrl, $pageUrl, $pagePath, $modePrefix);
                    $jobs = array_merge($jobs, $customJobs);
                    break;
            }
        }

        return array_values($jobs);
    }

    /**
     * カスタムコンテンツ用ジョブリストを構築する（MVP：一覧＋詳細）
     *
     * ブログと同型（Contents に plugin=BcCustomContent/type=CustomContent で登録）だが、
     * 詳細URLは `{url}view/{name|id}`、エントリーは動的テーブル（setup 必須）、RSS・アーカイブ無し。
     *
     * @param object $content Content エンティティ（entity_id = custom_contents.id）
     * @param int $siteId
     * @param array $targetConfig
     * @param string $exportPath
     * @param string $baseUrl
     * @param string $pageUrl
     * @param string $pagePath
     * @param string $modePrefix main_ / diff_
     * @param array $opts forceSingle: bool（差分では false=詳細は個別キューが担当）
     * @return array
     */
    private function buildCustomContentJobs(object $content, int $siteId, array $targetConfig, string $exportPath, string $baseUrl, string $pageUrl, string $pagePath, string $modePrefix = 'main_', array $opts = []): array
    {
        $jobs = [];
        $customContentId = (int) $content->entity_id;

        $info = $this->resolveCustomContent($customContentId);
        if (!$info) {
            $this->writeLog('[buildCustomContentJobs] custom_table_id を解決できません: content_id=' . ($content->id ?? '?'));
            return $jobs;
        }
        $tableId = $info['tableId'];
        $listCount = max((int) $info['listCount'], 1);

        $prefix = $modePrefix . $siteId . '_' . $customContentId;
        $needIndex = (bool) ($targetConfig['custom_index_' . $prefix] ?? true);
        $needSingle = (bool) ($targetConfig['custom_single_' . $prefix] ?? true);
        if (array_key_exists('forceSingle', $opts)) {
            $needSingle = (bool) $opts['forceSingle'];
        }

        $EntriesService = $this->getCustomEntriesService();
        $EntriesService->setup($tableId);

        // 一覧（＋ページネーション）。フロント一覧は index アクション（ブログと同じく /{url}index を取得）。
        if ($needIndex) {
            $indexUrl = $baseUrl . '/' . $pageUrl . 'index';
            $indexPath = $exportPath . $pagePath . 'index';
            $this->addJob($jobs, $indexUrl, $indexPath . '.html');
            $total = (int) $EntriesService->getIndex(['status' => 'publish'])->count();
            $this->addPagingJobs($jobs, $indexUrl, $indexPath, $total, $listCount);
        }

        // 詳細（view/{name|id}）。動的テーブルを最小列でチャンク走査（大量エントリーのメモリ抑制）。
        if ($needSingle) {
            $chunkSize = max(1, (int) (Configure::read('CuStatic.chunkSize') ?? 1000));
            $page = 1;
            do {
                $rows = $EntriesService->getIndex(['status' => 'publish'])
                    ->select(['CustomEntries.id', 'CustomEntries.name'], true)
                    ->limit($chunkSize)
                    ->page($page)
                    ->disableHydration()
                    ->all();

                $rowCount = 0;
                foreach ($rows as $row) {
                    $rowCount++;
                    $slug = !empty($row['name']) ? (string) $row['name'] : (string) $row['id'];
                    $targetUrl = $baseUrl . '/' . $pageUrl . 'view/' . $slug;
                    $targetPath = $exportPath . $pagePath . str_replace('/', DIRECTORY_SEPARATOR, 'view/' . $slug);
                    $this->addJob($jobs, $targetUrl, $targetPath . '.html');
                }
                $page++;
            } while ($rowCount === $chunkSize);
        }

        return array_values($jobs);
    }

    /**
     * Content(customContentId) から custom_table_id と list_count を解決する
     *
     * @param int $customContentId custom_contents.id（＝ Content.entity_id）
     * @return array{tableId:int, listCount:int}|null
     */
    private function resolveCustomContent(int $customContentId): ?array
    {
        $CustomContents = TableRegistry::getTableLocator()->get('BcCustomContent.CustomContents');
        $customContent = $CustomContents->find()->where(['CustomContents.id' => $customContentId])->first();
        if (!$customContent || empty($customContent->custom_table_id)) {
            return null;
        }
        return [
            'tableId' => (int) $customContent->custom_table_id,
            'listCount' => (int) ($customContent->list_count ?? 10),
        ];
    }

    /**
     * CustomEntriesService を取得する
     *
     * @return \BcCustomContent\Service\CustomEntriesServiceInterface
     */
    private function getCustomEntriesService()
    {
        return \BaserCore\Utility\BcContainer::get()
            ->get(\BcCustomContent\Service\CustomEntriesServiceInterface::class);
    }

    /**
     * 差分モードの計画（再生成ジョブ・削除対象パス・消し込み対象ID）を構築する
     *
     * キューの各行を action と実公開状態で振り分ける：
     *  - action=delete                           → 静的ファイル削除、行は消し込み
     *  - action=update かつ 公開中               → 再生成ジョブを積み、行は消し込み
     *  - action=update かつ 未来公開             → 生成せず行を保持（次回CRONで再判定＝簡易時限公開）
     *  - action=update かつ 非公開/期限切れ/消失 → 静的ファイル削除、行は消し込み
     *
     * @param array $siteIds
     * @param array $targetConfig
     * @param string $exportPath
     * @param string $baseUrl
     * @return array{jobs:array, deletePaths:array<int,string>, clearIds:array<int,int>}
     */
    private function buildDiffPlan(array $siteIds, array $targetConfig, string $exportPath, string $baseUrl): array
    {
        $jobs = [];
        $deletePaths = [];
        $clearIds = [];
        $expireIds = [];

        $queued = $this->CuStaticContents->find()
            ->where(['site_id IN' => $siteIds])
            ->all();

        foreach ($queued as $item) {
            $url = (string) $item->url;
            // 出力対象外に設定された種別は生成しない（行は消し込む）
            if (!$this->isQueuedItemTargetEnabled($item, $targetConfig, 'diff_')) {
                $clearIds[] = $item->id;
                continue;
            }

            $action = $item->action ?: 'update';

            if ($action === 'delete') {
                foreach ($this->staticPathsForItem($item, $exportPath) as $p) {
                    $deletePaths[] = $p;
                }
                $clearIds[] = $item->id;
                continue;
            }

            // action = expire: 生成済みで公開終了待ち。終了到来でファイル削除、未到来なら保持（再生成しない）
            if ($action === 'expire') {
                if ($url === '' || $this->resolvePublish($item)['state'] === 'gone') {
                    foreach ($this->staticPathsForItem($item, $exportPath) as $p) {
                        $deletePaths[] = $p;
                    }
                    $clearIds[] = $item->id;
                }
                continue;
            }

            // action = update: 実公開状態で分岐
            if ($url === '') {
                // URL 不明で再生成不能 → 消し込みのみ
                $clearIds[] = $item->id;
                continue;
            }
            $publish = $this->resolvePublish($item);
            if ($publish['state'] === 'future') {
                // 未来公開（開始前）→ 保留（キューに残し次回再判定）
                continue;
            }
            if ($publish['state'] === 'gone') {
                foreach ($this->staticPathsForItem($item, $exportPath) as $p) {
                    $deletePaths[] = $p;
                }
                $clearIds[] = $item->id;
                continue;
            }

            // live → 再生成ジョブ
            $this->addDiffJobsForItem($item, $targetConfig, $exportPath, $baseUrl, $jobs);
            if ($publish['futureEnd']) {
                // 公開終了が未来 → 生成後は expire に遷移させ、終了到来まで保持（次回以降は再生成せず削除だけ判定）
                $expireIds[] = $item->id;
            } else {
                $clearIds[] = $item->id;
            }
        }

        return [
            'jobs' => array_values($jobs),
            'deletePaths' => array_values(array_unique($deletePaths)),
            'clearIds' => $clearIds,
            'expireIds' => $expireIds,
        ];
    }

    /**
     * 差分：キュー1件分の再生成ジョブを積む
     *
     * BlogContent は従属ページ（一覧/カテゴリ/タグ/日付/著者/ページング）を再生成する。
     * 記事詳細は個別 BlogPost キューが担当するため、ここでは生成しない（forceSingle=false）。
     *
     * @param object $item キュー行
     * @param array $targetConfig
     * @param string $exportPath
     * @param string $baseUrl
     * @param array $jobs 追加先（参照渡し・パスキーで重複排除）
     * @return void
     */
    private function addDiffJobsForItem(object $item, array $targetConfig, string $exportPath, string $baseUrl, array &$jobs): void
    {
        $pageUrl = ltrim((string) $item->url, '/');
        $pagePath = str_replace('/', DIRECTORY_SEPARATOR, $pageUrl);

        switch ($item->type) {
            case 'ContentFolder':
                $this->addJob($jobs, $baseUrl . '/' . $pageUrl, $exportPath . $pagePath . 'index.html');
                break;
            case 'Page':
                $this->addJob($jobs, $baseUrl . '/' . $pageUrl, $exportPath . rtrim($pagePath, '/') . '.html');
                break;
            case 'BlogContent':
                $indexOne = (bool) ($targetConfig['blog_index_one_diff_' . $item->site_id . '_' . $item->entity_id] ?? false);
                $blogJobs = $this->buildBlogJobs(
                    (object) ['entity_id' => $item->entity_id],
                    (int) $item->site_id,
                    $targetConfig,
                    $exportPath,
                    $baseUrl,
                    $pageUrl,
                    $pagePath,
                    'diff_',
                    ['forceSingle' => false, 'indexOnePage' => $indexOne]
                );
                foreach ($blogJobs as $bj) {
                    $this->addJob($jobs, $bj['url'], $bj['path'], $bj['publish']);
                }
                break;
            case 'BlogPost':
                $this->addJob($jobs, $baseUrl . '/' . $pageUrl, $exportPath . rtrim($pagePath, '/') . '.html');
                break;
            case 'CustomContent':
                // 一覧・ページングを再生成（詳細は個別 CustomEntry キューが担当）
                $customJobs = $this->buildCustomContentJobs(
                    (object) ['entity_id' => $item->entity_id],
                    (int) $item->site_id,
                    $targetConfig,
                    $exportPath,
                    $baseUrl,
                    $pageUrl,
                    $pagePath,
                    'diff_',
                    ['forceSingle' => false]
                );
                foreach ($customJobs as $cj) {
                    $this->addJob($jobs, $cj['url'], $cj['path'], $cj['publish']);
                }
                break;
            case 'CustomEntry':
                $this->addJob($jobs, $baseUrl . '/' . $pageUrl, $exportPath . rtrim($pagePath, '/') . '.html');
                break;
        }
    }

    /**
     * キュー行の実公開状態と「未来の公開終了」有無を評価する
     *
     * @param object $item キュー行
     * @return array{state:string, futureEnd:bool}
     *   state: 'live' 公開中 / 'future' 公開開始前（保留） / 'gone' 非公開・期限切れ・消失
     *   futureEnd: 公開終了日時が未来にある（＝いずれ失効する）
     */
    private function resolvePublish(object $item): array
    {
        if ($item->type === 'BlogPost') {
            $BlogPosts = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
            $post = $BlogPosts->find()->where(['id' => $item->entity_id])->first();
            if (!$post) {
                return ['state' => 'gone', 'futureEnd' => false];
            }
            return $this->publishInfoOf((bool) $post->status, $post->publish_begin, $post->publish_end);
        }

        if ($item->type === 'CustomEntry') {
            // content_id は親 customContentId（BlogPost の content_id=blog_content_id に倣う）
            $info = $this->resolveCustomContent((int) $item->content_id);
            if (!$info) {
                return ['state' => 'gone', 'futureEnd' => false];
            }
            try {
                $EntriesService = $this->getCustomEntriesService();
                $EntriesService->setup($info['tableId']);
                $entry = $EntriesService->getIndex([])
                    ->where(['CustomEntries.id' => $item->entity_id])
                    ->first();
            } catch (\Throwable $e) {
                return ['state' => 'gone', 'futureEnd' => false];
            }
            if (!$entry) {
                return ['state' => 'gone', 'futureEnd' => false];
            }
            return $this->publishInfoOf((bool) $entry->status, $entry->publish_begin, $entry->publish_end);
        }

        // Page / ContentFolder / BlogContent / CustomContent は Contents で判定
        $content = $this->Contents->find()->where(['id' => $item->content_id])->first();
        if (!$content) {
            return ['state' => 'gone', 'futureEnd' => false];
        }
        return $this->publishInfoOf((bool) $content->status, $content->publish_begin, $content->publish_end);
    }

    /**
     * status / publish_begin / publish_end から公開状態と未来失効有無を判定する
     *
     * @param bool $status 有効な公開フラグ（親フォルダ等を反映した effective 値）
     * @param mixed $begin 公開開始日時（null 可）
     * @param mixed $end 公開終了日時（null 可）
     * @return array{state:string, futureEnd:bool}
     */
    private function publishInfoOf(bool $status, $begin, $end): array
    {
        $now = time();
        $beginTs = $begin ? strtotime((string) $begin) : null;
        $endTs = $end ? strtotime((string) $end) : null;
        $futureEnd = ($endTs !== null && $endTs > $now);

        if ($beginTs !== null && $beginTs > $now) {
            return ['state' => 'future', 'futureEnd' => $futureEnd];
        }
        if ($endTs !== null && $endTs < $now) {
            return ['state' => 'gone', 'futureEnd' => false];
        }
        return ['state' => $status ? 'live' : 'gone', 'futureEnd' => $futureEnd];
    }

    /**
     * キュー行に対応する静的ファイル/ディレクトリのパスを返す（削除用）
     *
     * @param object $item キュー行
     * @param string $exportPath
     * @return array<int,string>
     */
    private function staticPathsForItem(object $item, string $exportPath): array
    {
        $pageUrl = ltrim((string) $item->url, '/');
        $pagePath = str_replace('/', DIRECTORY_SEPARATOR, $pageUrl);

        switch ($item->type) {
            case 'ContentFolder':
                return [$exportPath . $pagePath . 'index.html'];
            case 'BlogContent':
            case 'CustomContent':
                // ブログ/カスタムコンテンツ全体の削除 → 配下ディレクトリごと削除
                return [rtrim($exportPath . $pagePath, DIRECTORY_SEPARATOR)];
            default:
                // Page / BlogPost / CustomEntry
                return [$exportPath . rtrim($pagePath, '/') . '.html'];
        }
    }

    /**
     * 出力先配下の静的ファイル/ディレクトリを安全に削除する
     *
     * exportPath 配下でないパスは（設定ミス等の事故防止のため）削除しない。
     *
     * @param string $path 削除対象
     * @param string $exportPath 出力先ルート
     * @return bool 実際に削除した場合 true（対象なし・配下外は false）
     */
    private function deleteStaticPath(string $path, string $exportPath): bool
    {
        $base = rtrim($exportPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $normalized = rtrim($path, DIRECTORY_SEPARATOR);
        // exportPath 配下限定（配下でない・ルート同一は拒否）
        if ($normalized === '' || strpos($normalized . DIRECTORY_SEPARATOR, $base) !== 0) {
            $this->writeLog(sprintf('[deleteStaticPath][%s] 出力先外のため削除しません: %s', $this->modeLabel, $path));
            return false;
        }

        if (is_dir($normalized)) {
            $this->deleteDirectory($normalized);
            $this->writeLog(sprintf('[deleteStaticPath][%s] ディレクトリ削除: %s', $this->modeLabel, $normalized));
            return true;
        }
        if (is_file($normalized)) {
            @unlink($normalized);
            $this->writeLog(sprintf('[deleteStaticPath][%s] ファイル削除: %s', $this->modeLabel, $normalized));
            return true;
        }

        // 対象が存在しない（既に削除済み等）。件数と実態がずれないよう明示的にログを残す。
        $this->writeLog(sprintf('[deleteStaticPath][%s] 対象なし（スキップ）: %s', $this->modeLabel, $normalized));
        return false;
    }

    /**
     * ブログ用ジョブリストを構築する
     *
     * @param object $content コンテンツエンティティ
     * @param int $siteId
     * @param array $targetConfig
     * @param string $exportPath
     * @param string $baseUrl
     * @param string $pageUrl
     * @param string $pagePath
     * @param string $modePrefix main_ / diff_
     * @param array $opts
     *   - forceSingle: bool 記事詳細生成を強制的に上書き（差分では false=詳細は別キューが担当）
     *   - indexOnePage: bool 記事一覧を1ページ目のみにする（#3 blog_index_one。ページング生成を抑制）
     * @return array
     */
    private function buildBlogJobs(object $content, int $siteId, array $targetConfig, string $exportPath, string $baseUrl, string $pageUrl, string $pagePath, string $modePrefix = 'main_', array $opts = []): array
    {
        $jobs = [];
        $blogContentId = $content->entity_id;
        $prefix = $modePrefix . $siteId . '_' . $blogContentId;
        $BlogContents = TableRegistry::getTableLocator()->get('BcBlog.BlogContents');

        $blogContent = $BlogContents->get($blogContentId);
        $listCount = max((int) ($blogContent->list_count ?? 10), 1);

        // どの集計が必要かを事前判定し、不要な集計・ジョブ蓄積を避ける
        $needIndex = (bool) ($targetConfig['blog_index_' . $prefix] ?? true);
        $needCategory = (bool) ($targetConfig['blog_category_' . $prefix] ?? true);
        $needTag = ($targetConfig['blog_tag_' . $prefix] ?? true) && !empty($blogContent->tag_use);
        $needAuthor = (bool) ($targetConfig['blog_author_' . $prefix] ?? true);
        $needSingle = (bool) ($targetConfig['blog_single_' . $prefix] ?? true);
        // 差分では記事詳細をブログ全体で再生成せず、個別 BlogPost キューが担当するため上書き可能にする
        if (array_key_exists('forceSingle', $opts)) {
            $needSingle = (bool) $opts['forceSingle'];
        }
        $indexOnePage = !empty($opts['indexOnePage']);

        $dateFormats = [];
        if ($targetConfig['blog_date_year_' . $prefix] ?? true) {
            $dateFormats[] = 'Y';
        }
        if ($targetConfig['blog_date_month_' . $prefix] ?? true) {
            $dateFormats[] = 'Y/m';
        }
        if ($targetConfig['blog_date_day_' . $prefix] ?? true) {
            // baserCMS の日付アーカイブはゼロ埋め（date('m') 準拠）のみ生成する
            $dateFormats[] = 'Y/m/d';
        }

        // 基底カラムのみをチャンク・ストリーミングで1パス集計する（アソシエーションをハイドレートしない）。
        // 記事詳細ジョブはこのパス内で直接 $jobs へ追加する。
        $agg = $this->aggregatePostsStreaming($blogContentId, [
            'dateFormats' => $dateFormats,
            'author' => $needAuthor,
            'category' => $needCategory,
            'single' => $needSingle,
        ], $jobs, $exportPath, $baseUrl, $pageUrl, $pagePath);

        $postCount = $agg['total'];

        // 記事一覧
        if ($needIndex) {
            $indexUrl = $baseUrl . '/' . $pageUrl . 'index';
            $indexPath = $exportPath . $pagePath . 'index';
            $this->addJob($jobs, $indexUrl, $indexPath . '.html');
            $this->addJob($jobs, $indexUrl . '.rss', $indexPath . '.rss');
            // #3 blog_index_one: 1ページ目のみのときは page-N.html を生成しない
            if (!$indexOnePage) {
                $this->addPagingJobs($jobs, $indexUrl, $indexPath, $postCount, $listCount);
            }
        }

        // カテゴリ別（子孫カテゴリの投稿も含めてロールアップ。フロント表示と件数・ページ数を一致させる）
        if ($needCategory) {
            $categoryCounts = $this->rollupCategoryCounts($blogContentId, $agg['categoryDirect']);
            foreach ($categoryCounts as $categoryName => $count) {
                $targetUrl = $baseUrl . '/' . $pageUrl . 'archives/category/' . $categoryName;
                $targetPath = $exportPath . $pagePath . str_replace('/', DIRECTORY_SEPARATOR, 'archives/category/' . $categoryName);
                $this->addJob($jobs, $targetUrl, $targetPath . '.html');
                $this->addPagingJobs($jobs, $targetUrl, $targetPath, $count, $listCount);
            }
        }

        // タグ別（多対多のため junction 経由の GROUP BY で件数のみ取得）
        if ($needTag) {
            foreach ($this->aggregateTagCounts($blogContentId) as $tagName => $count) {
                $targetUrl = $baseUrl . '/' . $pageUrl . 'archives/tag/' . $tagName;
                $targetPath = $exportPath . $pagePath . str_replace('/', DIRECTORY_SEPARATOR, 'archives/tag/' . $tagName);
                $this->addJob($jobs, $targetUrl, $targetPath . '.html');
                $this->addPagingJobs($jobs, $targetUrl, $targetPath, $count, $listCount);
            }
        }

        // 日付別（Y / Y/m / Y/m/d）
        foreach ($agg['dateCounts'] as $dateKey => $count) {
            $targetUrl = $baseUrl . '/' . $pageUrl . 'archives/date/' . $dateKey;
            $targetPath = $exportPath . $pagePath . str_replace('/', DIRECTORY_SEPARATOR, 'archives/date/' . $dateKey);
            $this->addJob($jobs, $targetUrl, $targetPath . '.html');
            $this->addPagingJobs($jobs, $targetUrl, $targetPath, $count, $listCount);
        }

        // 著者別（URL は user の id を使う。/archives/author/{id}）
        if ($needAuthor) {
            foreach ($agg['authorCounts'] as $authorId => $count) {
                $targetUrl = $baseUrl . '/' . $pageUrl . 'archives/author/' . $authorId;
                $targetPath = $exportPath . $pagePath . str_replace('/', DIRECTORY_SEPARATOR, 'archives/author/' . $authorId);
                $this->addJob($jobs, $targetUrl, $targetPath . '.html');
                $this->addPagingJobs($jobs, $targetUrl, $targetPath, $count, $listCount);
            }
        }

        return array_values($jobs);
    }

    /**
     * ブログ投稿を基底カラムのみでチャンク・ストリーミング集計する
     *
     * `contain` を使わず `blog_posts` のスカラーカラムだけを `limit/page` で逐次取得し、
     * 総件数・日付別件数・著者別件数・カテゴリ直下件数を集計しつつ、記事詳細ジョブを $jobs に直接追加する。
     * ハイドレート済み全件をメモリに載せないため、大量投稿でもピークメモリが増えない。
     *
     * @param int $blogContentId
     * @param array $flags dateFormats(array)/author(bool)/category(bool)/single(bool)
     * @param array $jobs 記事詳細ジョブの追加先（参照渡し）
     * @param string $exportPath
     * @param string $baseUrl
     * @param string $pageUrl
     * @param string $pagePath
     * @return array{total:int, dateCounts:array<string,int>, authorCounts:array<int,int>, categoryDirect:array<int,int>}
     */
    private function aggregatePostsStreaming(int $blogContentId, array $flags, array &$jobs, string $exportPath, string $baseUrl, string $pageUrl, string $pagePath): array
    {
        $BlogPosts = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        $chunkSize = max(1, (int) (Configure::read('CuStatic.chunkSize') ?? 1000));

        $dateFormats = $flags['dateFormats'] ?? [];
        $needAuthor = !empty($flags['author']);
        $needCategory = !empty($flags['category']);
        $needSingle = !empty($flags['single']);

        $total = 0;
        $dateCounts = [];
        $authorCounts = [];
        $categoryDirect = [];

        $page = 1;
        do {
            $rows = $BlogPosts->find()
                ->select([
                    'BlogPosts.id',
                    'BlogPosts.no',
                    'BlogPosts.name',
                    'BlogPosts.posted',
                    'BlogPosts.user_id',
                    'BlogPosts.blog_category_id',
                ])
                ->where($this->getBlogBaseConditions($blogContentId))
                ->orderBy(['BlogPosts.id' => 'ASC'])
                ->limit($chunkSize)
                ->page($page)
                ->disableHydration()
                ->all();

            $rowCount = 0;
            foreach ($rows as $row) {
                $rowCount++;
                $total++;

                if ($dateFormats && !empty($row['posted'])) {
                    $posted = $row['posted'];
                    foreach ($dateFormats as $fmt) {
                        // disableHydration でも型変換で DateTime になる場合があるため両対応
                        $key = $posted instanceof \DateTimeInterface
                            ? $posted->format($fmt)
                            : date($fmt, strtotime((string) $posted));
                        $dateCounts[$key] = ($dateCounts[$key] ?? 0) + 1;
                    }
                }

                if ($needAuthor && !empty($row['user_id'])) {
                    $authorId = (int) $row['user_id'];
                    $authorCounts[$authorId] = ($authorCounts[$authorId] ?? 0) + 1;
                }

                if ($needCategory && !empty($row['blog_category_id'])) {
                    $categoryId = (int) $row['blog_category_id'];
                    $categoryDirect[$categoryId] = ($categoryDirect[$categoryId] ?? 0) + 1;
                }

                if ($needSingle) {
                    // baserCMS5 の記事詳細URLはスラッグ（name カラム）優先、無ければ no（なければ id）。
                    // 参考: BcBlog\Service\BlogPostsService::getUrl()。id/no で書き出すと slug へ 302 され空ファイルになる。
                    $postSlug = !empty($row['name']) ? (string) $row['name'] : (string) ($row['no'] ?: $row['id']);
                    $targetUrl = $baseUrl . '/' . $pageUrl . 'archives/' . $postSlug;
                    $targetPath = $exportPath . $pagePath . str_replace('/', DIRECTORY_SEPARATOR, 'archives/' . $postSlug);
                    $this->addJob($jobs, $targetUrl, $targetPath . '.html');
                }
            }

            $page++;
        } while ($rowCount === $chunkSize);

        return [
            'total' => $total,
            'dateCounts' => $dateCounts,
            'authorCounts' => $authorCounts,
            'categoryDirect' => $categoryDirect,
        ];
    }

    /**
     * カテゴリ直下件数を子孫カテゴリ分も加算してロールアップする
     *
     * baserCMS のカテゴリアーカイブは子孫カテゴリの投稿も含んで表示される
     * （BcBlog\Service\BlogPostsService::createCategoryCondition() が子孫を IN 条件に加える）。
     * よって lft/rght による入れ子判定で子孫件数を親へ加算し、フロントの件数・ページ数と一致させる。
     * ロジックは BcBlog\Model\Table\BlogCategoriesTable::getCategoryPostCounts() に準拠。
     *
     * @param int $blogContentId
     * @param array<int,int> $directCounts カテゴリID => 直下件数
     * @return array<string,int> カテゴリ名 => ロールアップ後件数（件数 0 は除外）
     */
    private function rollupCategoryCounts(int $blogContentId, array $directCounts): array
    {
        $BlogCategories = TableRegistry::getTableLocator()->get('BcBlog.BlogCategories');
        $categories = $BlogCategories->find()
            ->select(['id', 'name', 'lft', 'rght'])
            ->where(['BlogCategories.blog_content_id' => $blogContentId])
            ->disableHydration()
            ->all()
            ->toList();
        if (!$categories) {
            return [];
        }

        $result = [];
        foreach ($categories as $category) {
            $categoryId = (int) $category['id'];
            $count = $directCounts[$categoryId] ?? 0;

            // 子カテゴリ（lft/rght が内側にあるもの）の件数を親へ加算
            foreach ($categories as $target) {
                if ((int) $target['id'] === $categoryId) {
                    continue;
                }
                if ($target['lft'] <= $category['lft'] || $target['rght'] >= $category['rght']) {
                    continue;
                }
                $count += $directCounts[(int) $target['id']] ?? 0;
            }

            if ($count > 0) {
                $result[(string) $category['name']] = $count;
            }
        }

        return $result;
    }

    /**
     * タグ別件数を junction 経由の GROUP BY で集計する（多対多）
     *
     * @param int $blogContentId
     * @return array<string,int> タグ名 => 件数
     */
    private function aggregateTagCounts(int $blogContentId): array
    {
        $BlogPosts = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        $query = $BlogPosts->find();
        $query
            ->select([
                'tag_name' => 'BlogTags.name',
                'cnt' => $query->func()->count('BlogPosts.id'),
            ])
            ->innerJoinWith('BlogTags')
            ->where($this->getBlogBaseConditions($blogContentId))
            ->groupBy(['BlogTags.name'])
            ->disableHydration();

        $result = [];
        foreach ($query->all() as $row) {
            $result[(string) $row['tag_name']] = (int) $row['cnt'];
        }

        return $result;
    }

    /**
     * ブログ投稿集計の共通WHERE条件（対象ブログ＋公開条件）を返す
     *
     * @param int $blogContentId
     * @return array
     */
    private function getBlogBaseConditions(int $blogContentId): array
    {
        $BlogPosts = TableRegistry::getTableLocator()->get('BcBlog.BlogPosts');
        return array_merge(
            ['BlogPosts.blog_content_id' => $blogContentId],
            $BlogPosts->getConditionAllowPublish()
        );
    }

    private function isQueuedItemTargetEnabled(object $item, array $targetConfig, string $modePrefix = 'diff_'): bool
    {
        // target_config の値は文字列（"1"/"0"）で保存されるため bool へ正規化する
        if ($item->type === 'ContentFolder') {
            return (bool) ($targetConfig[$modePrefix . 'folder_' . $item->site_id] ?? true);
        }
        if ($item->type === 'Page') {
            return (bool) ($targetConfig[$modePrefix . 'page_' . $item->site_id] ?? true);
        }
        if ($item->type === 'BlogContent') {
            return (bool) ($targetConfig[$modePrefix . 'blog_index_' . $item->site_id . '_' . $item->entity_id] ?? true);
        }
        if ($item->type === 'BlogPost') {
            return (bool) ($targetConfig[$modePrefix . 'blog_single_' . $item->site_id . '_' . $item->content_id] ?? true);
        }
        if ($item->type === 'CustomContent') {
            return (bool) ($targetConfig[$modePrefix . 'custom_index_' . $item->site_id . '_' . $item->entity_id] ?? true);
        }
        if ($item->type === 'CustomEntry') {
            return (bool) ($targetConfig[$modePrefix . 'custom_single_' . $item->site_id . '_' . $item->content_id] ?? true);
        }
        return true;
    }

    private function addPagingJobs(array &$jobs, string $baseUrl, string $basePath, int $itemCount, int $listCount): void
    {
        if ($itemCount <= $listCount || $listCount < 1) {
            return;
        }

        $pageMax = (int) ceil($itemCount / $listCount);
        $baseUrl = rtrim($baseUrl, '/');
        // ページ1は一覧本体（index.html 等）が該当するため2ページ目以降を生成する。
        // baserCMS5 のページャは ?page=N 形式（sort/direction/limit は既定値）のため取得URLもクエリで指定する。
        for ($page = 2; $page <= $pageMax; $page++) {
            $this->addJob(
                $jobs,
                $baseUrl . '?page=' . $page,
                $basePath . DIRECTORY_SEPARATOR . 'page-' . $page . '.html'
            );
        }
    }

    private function addJob(array &$jobs, string $url, string $path, bool $publish = true): void
    {
        $jobs[$path] = [
            'url' => $url,
            'path' => $path,
            'publish' => $publish,
        ];
    }

    /**
     * ディレクトリを再帰的にコピーする（rsync または PHP）
     *
     * @param string $src
     * @param string $dst
     * @param string $rsyncCommand rsyncコマンド文字列（空の場合はPHPコピー）
     * @return void
     */
    private function copyDirectory(string $src, string $dst, string $rsyncCommand = ''): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        if ($rsyncCommand !== '') {
            $cmd = sprintf('%s %s %s', escapeshellcmd($rsyncCommand), escapeshellarg($src), escapeshellarg($dst));
            exec($cmd, $output, $resultCode);
            if ($resultCode !== 0) {
                $this->writeLog('[copyDirectory] rsyncエラー: ' . implode("\n", $output));
            }
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($src));
            $target = $dst . ltrim($relativePath, DIRECTORY_SEPARATOR);
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * 出力先が破壊的削除しても安全なパスかを検証する
     *
     * 全件モードは出力先を `deleteDirectory()` で丸ごと消すため、設定ミスで
     * アプリ本体（ROOT）・webroot・config・plugins・vendor 等やその祖先、
     * ファイルシステム直下の浅いパスを指していると重大事故になる。該当時は例外を投げて中断する。
     *
     * @param string $exportPath 出力先フォルダパス
     * @return void
     * @throws \RuntimeException 危険なパスの場合
     */
    private function assertSafeExportPath(string $exportPath): void
    {
        $reason = \CuStatic\Utility\CuStaticUtil::unsafeReason($exportPath);
        if ($reason !== null) {
            throw new RuntimeException('出力先が安全でないため削除を中断しました: ' . $reason . ' (' . $exportPath . ')');
        }
    }

    /**
     * ディレクトリを再帰的に削除する
     *
     * @param string $path
     * @return void
     */
    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }

}
