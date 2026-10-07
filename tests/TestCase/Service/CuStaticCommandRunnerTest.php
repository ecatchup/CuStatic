<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Test\TestCase\Service;

use BaserCore\TestSuite\BcTestCase;
use Cake\Core\Configure;
use CuStatic\Service\CuStaticCommandRunner;

/**
 * CuStaticCommandRunnerTest
 *
 * 書き出し後コマンドの設定正規化・実行条件・実行結果を検証する。
 */
class CuStaticCommandRunnerTest extends BcTestCase
{

    /**
     * @var CuStaticCommandRunner
     */
    protected $Runner;

    /**
     * @var string
     */
    protected string $workDir;

    public function setUp(): void
    {
        parent::setUp();
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('シェルコマンドを使うため Windows ではスキップします。');
        }
        $this->Runner = new CuStaticCommandRunner();
        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cu_static_cmd_' . getmypid();
        if (!is_dir($this->workDir)) {
            mkdir($this->workDir, 0777, true);
        }
    }

    public function tearDown(): void
    {
        Configure::delete('CuStatic.afterExportCommands');
        Configure::delete('CuStatic.afterExportCommandTimeout');
        foreach (glob($this->workDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->workDir)) {
            rmdir($this->workDir);
        }
        unset($this->Runner);
        parent::tearDown();
    }

    /**
     * 文字列・配列の両形式を正規化し、空のコマンドは除外する
     */
    public function testGetCommands(): void
    {
        Configure::write('CuStatic.afterExportCommandTimeout', 120);
        Configure::write('CuStatic.afterExportCommands', [
            'echo a',
            ['command' => 'echo b', 'label' => 'B', 'modes' => ['main'], 'skipIfNoChange' => false, 'timeout' => 10, 'stopOnError' => true],
            ['command' => ''],
            123,
        ]);
        $commands = CuStaticCommandRunner::getCommands();
        $this->assertCount(2, $commands);
        $this->assertSame('echo a', $commands[0]['label']);
        $this->assertSame(['all', 'diff'], $commands[0]['modes']);
        $this->assertTrue($commands[0]['skipIfNoChange']);
        $this->assertSame(120, $commands[0]['timeout']);
        $this->assertSame('B', $commands[1]['label']);
        $this->assertSame(['all'], $commands[1]['modes']);
        $this->assertFalse($commands[1]['skipIfNoChange']);
        $this->assertSame(10, $commands[1]['timeout']);
        $this->assertTrue($commands[1]['stopOnError']);
        $this->assertSame([], CuStaticCommandRunner::getCommands([]));
    }

    /**
     * 直書きのトークンはマスクし、環境変数参照は残す
     */
    public function testMaskCommand(): void
    {
        $this->assertSame(
            'curl -H "Authorization: Bearer ***" https://api.example.com',
            CuStaticCommandRunner::maskCommand('curl -H "Authorization: Bearer abcDEF123" https://api.example.com')
        );
        $this->assertSame(
            'curl -H "Authorization: Bearer $CF_API_TOKEN"',
            CuStaticCommandRunner::maskCommand('curl -H "Authorization: Bearer $CF_API_TOKEN"')
        );
        $this->assertSame('deploy --token=*** --api-key ***', CuStaticCommandRunner::maskCommand('deploy --token=xyz --api-key secret1'));
        $this->assertSame('ls -la', CuStaticCommandRunner::maskCommand('ls -la'));
    }

    /**
     * 環境変数・作業ディレクトリが渡り、標準出力と終了コードを取得できる
     */
    public function testRunAllPassesEnvironment(): void
    {
        $results = $this->Runner->runAll($this->context(), null, [
            ['command' => 'echo "$CU_STATIC_MODE|$CU_STATIC_EXPORT_PATH|$CU_STATIC_JOB_COUNT|$CU_STATIC_DELETE_COUNT|$CU_STATIC_PUBLIC_URL|$CU_STATIC_BASE_URL|$EXTRA|$(pwd)"', 'env' => ['EXTRA' => 'x']],
        ]);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertSame(0, $results[0]['exitCode']);
        $this->assertSame(
            'all|' . $this->workDir . '|3|1|https://www.example.com|https://admin.example.com|x|' . realpath($this->workDir),
            trim($results[0]['stdout'])
        );
    }

    /**
     * 失敗してもその後のコマンドは実行する。stopOnError 指定時は後続を実行しない
     */
    public function testRunAllFailureAndStopOnError(): void
    {
        $results = $this->Runner->runAll($this->context(), null, ['echo err >&2; exit 3', 'echo next']);
        $this->assertFalse($results[0]['success']);
        $this->assertSame(3, $results[0]['exitCode']);
        $this->assertSame('err', trim($results[0]['stderr']));
        $this->assertTrue($results[1]['success']);

        $results = $this->Runner->runAll($this->context(), null, [
            ['command' => 'exit 1', 'stopOnError' => true],
            'echo next',
        ]);
        $this->assertFalse($results[0]['success']);
        $this->assertTrue($results[1]['skipped']);
    }

    /**
     * タイムアウトしたコマンドは子孫プロセスも含めて停止し、失敗扱いになる
     */
    public function testRunTimeout(): void
    {
        $marker = $this->workDir . DIRECTORY_SEPARATOR . 'timeout_marker';
        $start = microtime(true);
        $results = $this->Runner->runAll($this->context(), null, [
            ['command' => 'sleep 2; touch ' . escapeshellarg($marker), 'timeout' => 1],
        ]);
        $this->assertTrue($results[0]['timedOut']);
        $this->assertFalse($results[0]['success']);
        $this->assertLessThan(2, microtime(true) - $start);

        // シェルの子（sleep 後の touch）まで停止していれば、待ってもマーカーは作られない
        sleep(2);
        $this->assertFileDoesNotExist($marker);
    }

    /**
     * モード指定と、変更のない差分実行のスキップ
     */
    public function testRunAllModeAndNoChange(): void
    {
        $commands = [
            ['command' => 'echo all-only', 'modes' => ['all']],
            ['command' => 'echo diff-skip'],
            ['command' => 'echo diff-always', 'skipIfNoChange' => false],
        ];

        $noChange = $this->context(['mode' => 'diff', 'jobCount' => 0, 'deleteCount' => 0]);
        $results = $this->Runner->runAll($noChange, null, $commands);
        $this->assertSame(['diff-always'], array_map(fn($r) => trim($r['stdout']), $results));

        $changed = $this->context(['mode' => 'diff', 'jobCount' => 0, 'deleteCount' => 2]);
        $results = $this->Runner->runAll($changed, null, $commands);
        $this->assertSame(['diff-skip', 'diff-always'], array_map(fn($r) => trim($r['stdout']), $results));
    }

    /**
     * 作業ディレクトリが存在しない場合は実行せず失敗扱い
     */
    public function testRunMissingCwd(): void
    {
        $results = $this->Runner->runAll($this->context(), null, [['command' => 'echo x', 'cwd' => '/no/such/cu_static_dir']]);
        $this->assertFalse($results[0]['success']);
        $this->assertSame('', $results[0]['stdout']);
    }

    /**
     * 実行コンテキストを生成する
     *
     * @param array $override
     * @return array
     */
    private function context(array $override = []): array
    {
        return array_merge([
            'exportPath' => $this->workDir . DIRECTORY_SEPARATOR,
            'mode' => 'all',
            'jobCount' => 3,
            'deleteCount' => 1,
            'publicUrl' => 'https://www.example.com',
            'baseUrl' => 'https://admin.example.com',
        ], $override);
    }

}
