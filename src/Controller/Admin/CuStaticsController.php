<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Controller\Admin;

use BaserCore\Utility\BcContainerTrait;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Log\Log;
use CuStatic\Service\CuStaticConfigServiceInterface;
use CuStatic\Service\CuStaticServiceInterface;

/**
 * CuStaticsController
 *
 * 静的HTML出力プラグインの管理画面コントローラ。
 */
class CuStaticsController extends CuStaticAppController
{

    use BcContainerTrait;

    /**
     * [ADMIN] index
     *
     * 静的HTML出力実行画面。
     * ボタン押下で bin/cake cu_static main をバックグラウンド実行する。
     *
     * @return \Cake\Http\Response|null
     */
    public function index(): ?Response
    {
        if ($this->request->is('post')) {
            /** @var CuStaticConfigServiceInterface $configService */
            $configService = $this->getService(CuStaticConfigServiceInterface::class);
            $config = $configService->getConfig();

            if (!$config) {
                $this->BcMessage->setError('オプション設定が未設定です。先に設定を保存してください。');
                return $this->redirect(['action' => 'config']);
            }

            if ($config->status) {
                $this->BcMessage->setError('現在処理中です。しばらくお待ちください。');
                return $this->redirect(['action' => 'index']);
            }

            // 実行モード（main=全件 / diff=差分）。差分は CuStatic.cronEnabled が有効な場合のみ許可。
            $mode = $this->request->getData('mode') === 'diff' ? 'diff' : 'main';
            if ($mode === 'diff' && !Configure::read('CuStatic.cronEnabled')) {
                $this->BcMessage->setError('差分出力は無効です（CuStatic.cronEnabled）。');
                return $this->redirect(['action' => 'index']);
            }

            // バックグラウンドでコマンドを実行
            $workers = Configure::read('CuStatic.defaultWorkers') ?? 4;
            $cakePath = ROOT . DS . 'bin' . DS . 'cake.php';

            // PHP_BINARY は FPM コンテキストでは空または fpm バイナリになるため、CLI PHP を確実に取得する
            $phpBin = PHP_BINARY;
            if (empty($phpBin) || stripos($phpBin, 'fpm') !== false) {
                $phpBin = trim((string)shell_exec('which php 2>/dev/null')) ?: '/usr/local/bin/php';
            }

            // コンソール出力は /dev/null へ捨てる。
            // CakePHP はコマンド実行時にログをコンソールへもミラー出力するため、
            // cu_static.log へリダイレクトすると File ログエンジンの出力と二重化する。
            // 進捗・詳細ログは File エンジン経由で cu_static.log に記録される。
            $cmd = sprintf(
                'nohup %s %s cu_static %s --workers=%d > /dev/null 2>&1 &',
                escapeshellarg($phpBin),
                escapeshellarg($cakePath),
                escapeshellarg($mode),
                (int) $workers
            );
            exec($cmd);

            $label = $mode === 'diff' ? '差分出力' : '静的HTML出力';
            $this->BcMessage->setSuccess($label . 'を開始しました。ログで進捗を確認してください。');
            return $this->redirect(['action' => 'index']);
        }

        /** @var CuStaticConfigServiceInterface $configService */
        $configService = $this->getService(CuStaticConfigServiceInterface::class);
        $config = $configService->getConfig();
        $this->set('config', $config);

        return null;
    }

    /**
     * [ADMIN] config
     *
     * オプション設定画面。
     *
     * @return \Cake\Http\Response|null
     */
    public function config(): ?Response
    {
        /** @var CuStaticConfigServiceInterface $configService */
        $configService = $this->getService(CuStaticConfigServiceInterface::class);
        // フォームには常にエンティティを渡す（未保存時は空エンティティ。null/false を渡すと FormHelper が例外を投げる）
        $config = $configService->getConfig() ?? $this->fetchTable('CuStatic.CuStaticConfigs')->newEmptyEntity();

        if ($this->request->is(['post', 'put'])) {
            $data = $this->request->getData();
            // チェックボックスが全てOFFの場合はキー自体が送信されないため、明示的に空配列を入れる
            $data['target_config'] = $data['target_config'] ?? [];
            // status_change チェックなしの場合は status を変更させない
            if (empty($data['status_change'])) {
                unset($data['status']);
            }
            unset($data['status_change']);
            $saved = $configService->saveConfig($data);
            if ($saved && !$saved->getErrors()) {
                $this->BcMessage->setSuccess('オプション設定を保存しました。');
                return $this->redirect(['action' => 'config']);
            }
            // 保存失敗時はバリデーションエラーを保持したエンティティを再表示する
            $config = $saved ?: $config;
            $this->BcMessage->setError('入力エラーです。内容を修正してください。');
        }

        $this->set('config', $config);

        // サイト一覧
        $Sites = $this->fetchTable('BaserCore.Sites');
        $sites = $Sites->find()
            ->where(['status' => true])
            ->orderBy(['id' => 'ASC'])
            ->all()
            ->combine('id', 'display_name')
            ->toArray();
        $this->set('sites', $sites);

        // ブログコンテンツ一覧
        $Contents = $this->fetchTable('BaserCore.Contents');
        $blogContents = $Contents->find()
            ->where(['plugin' => 'BcBlog', 'type' => 'BlogContent'])
            ->orderBy(['site_id' => 'ASC', 'entity_id' => 'ASC'])
            ->all();

        $blogContentsBySite = [];
        foreach ($blogContents as $content) {
            $blogContentsBySite[$content->site_id][] = $content;
        }
        $this->set('blogContentsBySite', $blogContentsBySite);

        // カスタムコンテンツ一覧（サイト別）
        $customContents = $Contents->find()
            ->where(['plugin' => 'BcCustomContent', 'type' => 'CustomContent'])
            ->orderBy(['site_id' => 'ASC', 'entity_id' => 'ASC'])
            ->all();

        $customContentsBySite = [];
        foreach ($customContents as $content) {
            $customContentsBySite[$content->site_id][] = $content;
        }
        $this->set('customContentsBySite', $customContentsBySite);

        return null;
    }

    /**
     * [ADMIN] get_status
     *
     * 実行状態・進捗、および `offset` 以降の差分ログを JSON で返す（1エンドポイントに統合）。
     * ログはプレーンテキストで返し、表示側（textContent）でエスケープする。
     *
     * @return \Cake\Http\Response
     */
    public function get_status(): Response
    {
        $this->autoRender = false;

        /** @var CuStaticConfigServiceInterface $configService */
        $configService = $this->getService(CuStaticConfigServiceInterface::class);
        $config = $configService->getConfig();

        $offset = (int) $this->request->getQuery('offset', 0);
        [$log, $newOffset] = $this->readLogFrom(LOGS . 'cu_static.log', $offset);

        // 開始／終了時刻。経過秒は「終了時刻（未完了なら現在時刻）− 開始時刻」で算出する。
        $started = $config && $config->started ? $config->started : null;
        $finished = $config && $config->finished ? $config->finished : null;
        $elapsed = null;
        if ($started) {
            $endTs = $finished ? $finished->getTimestamp() : time();
            $elapsed = max(0, $endTs - $started->getTimestamp());
        }

        $result = [
            'status' => $config ? (int) $config->status : 0,
            'progress' => $config ? (int) $config->progress : 0,
            'progress_max' => $config ? (int) $config->progress_max : 0,
            'started' => $started ? $started->format('Y-m-d H:i:s') : '',
            'finished' => $finished ? $finished->format('Y-m-d H:i:s') : '',
            'elapsed' => $elapsed,
            'log' => $log,
            'offset' => $newOffset,
        ];

        return $this->response
            ->withType('application/json')
            ->withStringBody((string) json_encode($result));
    }

    /**
     * ログファイルを指定オフセット以降だけ読み出す（増分取得・行単位）
     *
     * - 初回（offset<=0）は末尾 8KB のみ返す（初期ペイロードを抑制）
     * - ローテート/切り詰めで size<offset の場合は先頭から読み直す
     * - 完全な行（末尾が改行）のみ返し、行の途中は次回へ持ち越す。
     *   これにより表示側で行の途中が切れて見えるのを防ぐ。
     *
     * @param string $logFile
     * @param int $offset 前回までに読んだバイト位置
     * @return array{0: string, 1: int} [差分ログ, 新オフセット（＝最後に返した改行の直後）]
     */
    private function readLogFrom(string $logFile, int $offset): array
    {
        if (!is_file($logFile)) {
            return ['', 0];
        }
        $size = (int) filesize($logFile);
        if ($size <= 0) {
            return ['', 0];
        }

        $initialTail = 8000; // 初回表示は末尾8KBのみ
        if ($offset <= 0) {
            $start = ($size > $initialTail) ? $size - $initialTail : 0;
        } elseif ($offset > $size) {
            $start = 0; // 切り詰め/ローテート → 先頭から
        } else {
            $start = $offset;
        }

        if ($size <= $start) {
            return ['', $start];
        }

        $chunk = (string) file_get_contents($logFile, false, null, $start, $size - $start);

        // 初回の末尾tailで先頭から読んでいない場合、途中で始まる先頭行を捨てる
        if ($offset <= 0 && $start > 0) {
            $nlPos = strpos($chunk, "\n");
            if ($nlPos === false) {
                // 8KB内に改行なし（極端に長い1行）→ 完全な行が無いので今回は返さない
                return ['', $start];
            }
            $start += $nlPos + 1;
            $chunk = substr($chunk, $nlPos + 1);
        }

        // 末尾の未完成行（改行で終わらない分）は次回へ持ち越す
        $lastNl = strrpos($chunk, "\n");
        if ($lastNl === false) {
            // 完全な行がまだ無い
            return ['', $start];
        }
        $log = substr($chunk, 0, $lastNl + 1);

        return [$log, $start + $lastNl + 1];
    }

    /**
     * [ADMIN] log_download
     *
     * ログファイルをダウンロードする。
     *
     * @return \Cake\Http\Response
     */
    public function log_download(): Response
    {
        $this->autoRender = false;

        $logFile = LOGS . 'cu_static.log';

        if (!file_exists($logFile)) {
            touch($logFile);
        }

        return $this->response
            ->withFile($logFile, ['download' => true, 'name' => 'cu_static.log']);
    }

}
