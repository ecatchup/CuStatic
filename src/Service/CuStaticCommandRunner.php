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
use Cake\Log\Log;

/**
 * CuStaticCommandRunner
 *
 * 書き出し完了後に、設定 `CuStatic.afterExportCommands` のコマンドを順に実行する。
 * CDN キャッシュの削除（Cloudflare 等）やデプロイ（wrangler deploy 等）を想定している。
 *
 * コマンドはサーバ上の設定ファイル（setting_customize.php）でのみ定義でき、
 * 管理画面からは登録できない（管理画面から任意のシェルコマンドを実行できる経路を作らないため）。
 *
 * 各コマンドには次の環境変数を渡す（親プロセスの環境変数も引き継ぐ）:
 *   CU_STATIC_EXPORT_PATH / CU_STATIC_MODE / CU_STATIC_JOB_COUNT / CU_STATIC_DELETE_COUNT /
 *   CU_STATIC_PUBLIC_URL / CU_STATIC_BASE_URL
 *
 * コマンドの失敗（終了コード 0 以外・タイムアウト）は書き出し自体の失敗にはしない。
 */
class CuStaticCommandRunner
{

    /**
     * ログ・標準出力の記録上限（文字数）
     */
    private const OUTPUT_LIMIT = 10000;

    /**
     * 設定 `CuStatic.afterExportCommands` を正規化して返す
     *
     * 各要素は文字列（コマンドのみ）または配列:
     *   - command: string 実行するコマンド（必須）
     *   - label: string ログ・管理画面に表示する名前（省略時はトークン等をマスクしたコマンド）
     *   - cwd: string 作業ディレクトリ（省略時は出力先）
     *   - modes: array 実行するモード（all / diff。main は all の別名。省略時は両方）
     *   - skipIfNoChange: bool 差分で変更（出力・削除）が0件ならスキップ（既定 true）
     *   - timeout: int タイムアウト秒（既定 CuStatic.afterExportCommandTimeout = 300）
     *   - stopOnError: bool 失敗時に後続のコマンドを実行しない（既定 false）
     *   - env: array 追加の環境変数
     *
     * @param array|null $commands 未指定時は Configure から読む
     * @return array<int, array{command:string, label:string, cwd:string, modes:array, skipIfNoChange:bool, timeout:int, stopOnError:bool, env:array}>
     */
    public static function getCommands(?array $commands = null): array
    {
        $commands ??= (array) (Configure::read('CuStatic.afterExportCommands') ?? []);
        $defaultTimeout = max(1, (int) (Configure::read('CuStatic.afterExportCommandTimeout') ?? 300));

        $result = [];
        foreach ($commands as $item) {
            if (is_string($item)) {
                $item = ['command' => $item];
            }
            if (!is_array($item) || trim((string) ($item['command'] ?? '')) === '') {
                continue;
            }
            $modes = array_map(
                fn($m) => $m === 'main' ? 'all' : (string) $m,
                (array) ($item['modes'] ?? ['all', 'diff'])
            );
            $command = trim((string) $item['command']);
            $result[] = [
                'command' => $command,
                'label' => trim((string) ($item['label'] ?? '')) ?: self::maskCommand($command),
                'cwd' => (string) ($item['cwd'] ?? ''),
                'modes' => array_values(array_unique($modes)),
                'skipIfNoChange' => (bool) ($item['skipIfNoChange'] ?? true),
                'timeout' => max(1, (int) ($item['timeout'] ?? $defaultTimeout)),
                'stopOnError' => (bool) ($item['stopOnError'] ?? false),
                'env' => (array) ($item['env'] ?? []),
            ];
        }
        return $result;
    }

    /**
     * ログ・画面表示用に、コマンド文字列中のトークン等をマスクする
     *
     * 環境変数参照（$CF_API_TOKEN 等）はそのまま残る。秘密情報はコマンドへ直書きせず
     * 環境変数で渡すことを推奨する。
     *
     * @param string $command
     * @return string
     */
    public static function maskCommand(string $command): string
    {
        $command = (string) preg_replace('/(Bearer\s+)(?!\$)[^\s"\']+/i', '$1***', $command);
        return (string) preg_replace(
            '/((?:token|secret|password|passwd|api[_-]?key|auth[_-]?key)["\']?\s*[=:]\s*["\']?|--(?:token|password|api-key)[=\s]+["\']?)(?!\$)[^\s"\'&]+/i',
            '$1***',
            $command
        );
    }

    /**
     * 書き出し完了後のコマンドを順に実行する
     *
     * @param array $context exportPath / mode（all|diff）/ jobCount / deleteCount / publicUrl / baseUrl
     * @param CuStaticProgressReporter|null $progress 実行件数を進捗バーへ反映する
     * @param array|null $commands 未指定時は Configure から読む
     * @return array<int, array> 各コマンドの実行結果（skipped / exitCode / timedOut / seconds 等）
     */
    public function runAll(array $context, ?CuStaticProgressReporter $progress = null, ?array $commands = null): array
    {
        $mode = (string) ($context['mode'] ?? 'all');
        $noChange = $mode === 'diff'
            && (int) ($context['jobCount'] ?? 0) === 0
            && (int) ($context['deleteCount'] ?? 0) === 0;

        $targets = [];
        foreach (self::getCommands($commands) as $command) {
            if (!in_array($mode, $command['modes'], true)) {
                continue;
            }
            if ($noChange && $command['skipIfNoChange']) {
                $this->log('info', sprintf('[afterExportCommand] 変更がないためスキップ: %s', $command['label']));
                continue;
            }
            $targets[] = $command;
        }
        if (!$targets) {
            return [];
        }

        if ($progress) {
            $progress->reserve(count($targets));
        }

        $exportPath = rtrim((string) ($context['exportPath'] ?? ''), DIRECTORY_SEPARATOR);
        $env = [
            'CU_STATIC_EXPORT_PATH' => $exportPath,
            'CU_STATIC_MODE' => $mode,
            'CU_STATIC_JOB_COUNT' => (string) (int) ($context['jobCount'] ?? 0),
            'CU_STATIC_DELETE_COUNT' => (string) (int) ($context['deleteCount'] ?? 0),
            'CU_STATIC_PUBLIC_URL' => (string) ($context['publicUrl'] ?? ''),
            'CU_STATIC_BASE_URL' => (string) ($context['baseUrl'] ?? ''),
        ];

        $results = [];
        $stopped = false;
        foreach ($targets as $command) {
            if ($stopped) {
                $this->log('info', sprintf('[afterExportCommand] 前のコマンドが失敗したため実行しません: %s', $command['label']));
                $results[] = ['label' => $command['label'], 'skipped' => true];
                if ($progress) {
                    $progress->advance();
                }
                continue;
            }

            $result = $this->run($command, $env, $exportPath);
            $results[] = $result;
            if ($progress) {
                $progress->advance();
            }
            if (!$result['success'] && $command['stopOnError']) {
                $stopped = true;
            }
        }
        return $results;
    }

    /**
     * コマンドを1件実行する
     *
     * @param array $command getCommands() で正規化した1件
     * @param array<string, string> $env 追加する環境変数
     * @param string $defaultCwd cwd 未指定時の作業ディレクトリ
     * @return array{label:string, skipped:bool, success:bool, exitCode:int, timedOut:bool, seconds:float, stdout:string, stderr:string}
     */
    public function run(array $command, array $env, string $defaultCwd): array
    {
        $label = $command['label'];
        $cwd = $command['cwd'] !== '' ? $command['cwd'] : $defaultCwd;
        $result = [
            'label' => $label,
            'skipped' => false,
            'success' => false,
            'exitCode' => -1,
            'timedOut' => false,
            'seconds' => 0.0,
            'stdout' => '',
            'stderr' => '',
        ];

        if ($cwd === '' || !is_dir($cwd)) {
            $this->log('error', sprintf('[afterExportCommand] 作業ディレクトリが存在しません（%s）: %s', $cwd, $label));
            return $result;
        }

        $this->log('info', sprintf('[afterExportCommand] 実行開始: %s', $label));
        $start = microtime(true);

        $processEnv = array_merge(getenv() ?: [], array_map('strval', $command['env']), $env);
        $process = proc_open(
            $command['command'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd,
            $processEnv
        );
        if (!is_resource($process)) {
            $this->log('error', sprintf('[afterExportCommand] 起動に失敗しました: %s', $label));
            return $result;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = $start + $command['timeout'];
        $exitCode = -1;
        while (true) {
            $result['stdout'] .= (string) stream_get_contents($pipes[1]);
            $result['stderr'] .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = (int) $status['exitcode'];
                break;
            }
            if (microtime(true) >= $deadline) {
                $result['timedOut'] = true;
                // コマンドはシェル経由で起動するため、シェルだけでなく子孫プロセスもまとめて停止する
                $pids = $this->collectProcessTree((int) $status['pid']);
                $this->signalProcesses($pids, 15);
                proc_terminate($process);
                // SIGTERM で終了しない場合に備えて少し待ってから強制終了する
                usleep(500000);
                $this->signalProcesses($pids, 9);
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }
                break;
            }
            $read = [$pipes[1], $pipes[2]];
            $write = $except = null;
            @stream_select($read, $write, $except, 0, 200000);
        }
        $result['stdout'] .= (string) stream_get_contents($pipes[1]);
        $result['stderr'] .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closeCode = proc_close($process);
        // proc_get_status が終了を検知する前に exitcode を取りこぼした場合は proc_close の値を使う
        if ($exitCode === -1 && !$result['timedOut']) {
            $exitCode = $closeCode;
        }

        $result['exitCode'] = $exitCode;
        $result['seconds'] = round(microtime(true) - $start, 2);
        $result['success'] = !$result['timedOut'] && $exitCode === 0;
        $result['stdout'] = self::truncate($result['stdout']);
        $result['stderr'] = self::truncate($result['stderr']);

        if ($result['timedOut']) {
            $this->log('error', sprintf('[afterExportCommand] タイムアウト（%d秒）のため停止しました: %s', $command['timeout'], $label));
        } else {
            $this->log(
                $result['success'] ? 'info' : 'error',
                sprintf('[afterExportCommand] %s（exit=%d, %.2f秒）: %s', $result['success'] ? '完了' : '失敗', $exitCode, $result['seconds'], $label)
            );
        }
        if (trim($result['stdout']) !== '') {
            $this->log('info', '[afterExportCommand] stdout: ' . trim($result['stdout']));
        }
        if (trim($result['stderr']) !== '') {
            $this->log($result['success'] ? 'info' : 'error', '[afterExportCommand] stderr: ' . trim($result['stderr']));
        }

        return $result;
    }

    /**
     * 指定プロセスの子孫プロセスIDを列挙する（pgrep が使えない環境では空）
     *
     * 親が先に終了すると子孫の親子関係が辿れなくなるため、停止前に収集しておく。
     *
     * @param int $pid
     * @return array<int, int>
     */
    private function collectProcessTree(int $pid): array
    {
        if ($pid <= 0 || DIRECTORY_SEPARATOR === '\\') {
            return [];
        }
        $result = [];
        $children = trim((string) @shell_exec('pgrep -P ' . $pid . ' 2>/dev/null'));
        foreach (preg_split('/\s+/', $children) ?: [] as $child) {
            if (ctype_digit($child)) {
                $result[] = (int) $child;
                $result = array_merge($result, $this->collectProcessTree((int) $child));
            }
        }
        return $result;
    }

    /**
     * プロセス群へシグナルを送る（終了済みのプロセスは無視）
     *
     * @param array<int, int> $pids
     * @param int $signal
     * @return void
     */
    private function signalProcesses(array $pids, int $signal): void
    {
        foreach ($pids as $pid) {
            if (function_exists('posix_kill')) {
                @posix_kill($pid, $signal);
            } else {
                @exec(sprintf('kill -%d %d 2>/dev/null', $signal, $pid));
            }
        }
    }

    /**
     * 長い出力を末尾を残して切り詰める
     *
     * @param string $output
     * @return string
     */
    private static function truncate(string $output): string
    {
        if (mb_strlen($output) <= self::OUTPUT_LIMIT) {
            return $output;
        }
        return '...(省略)...' . mb_substr($output, -self::OUTPUT_LIMIT);
    }

    /**
     * cu_static.log へ書き出す
     *
     * @param string $level
     * @param string $message
     * @return void
     */
    protected function log(string $level, string $message): void
    {
        Log::write($level, sprintf('[pid:%d] %s', getmypid(), $message), ['scope' => ['cu_static']]);
    }

}
