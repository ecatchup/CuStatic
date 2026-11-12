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
use CuStatic\Service\CuStaticService;

/**
 * CuStaticServiceTest
 *
 * I/O を伴わない純粋ロジック（ベースURL決定）を検証する。
 * href の静的パス変換は CuStaticUtil へ移設したため CuStaticUtilTest で検証する。
 *
 * @property CuStaticService $CuStaticService
 */
class CuStaticServiceTest extends BcTestCase
{

    /**
     * @var CuStaticService
     */
    protected $CuStaticService;

    public function setUp(): void
    {
        parent::setUp();
        $this->CuStaticService = new CuStaticService();
    }

    public function tearDown(): void
    {
        unset($this->CuStaticService);
        parent::tearDown();
    }

    /**
     * 設定値が渡された場合は末尾スラッシュを除去してそのまま返す
     */
    public function testGetBaseUrlUsesConfigValue(): void
    {
        $this->assertSame('https://example.com', $this->CuStaticService->getBaseUrl('https://example.com/'));
        $this->assertSame('https://example.com', $this->CuStaticService->getBaseUrl('https://example.com'));
    }

    /**
     * 設定値が空なら BcEnv.sslUrl → siteUrl の順で採用する
     */
    public function testGetBaseUrlFallsBackToBcEnv(): void
    {
        Configure::write('BcEnv.sslUrl', 'https://ssl.example.com/');
        Configure::write('BcEnv.siteUrl', 'https://site.example.com/');
        $this->assertSame('https://ssl.example.com', $this->CuStaticService->getBaseUrl(''));

        Configure::write('BcEnv.sslUrl', '');
        $this->assertSame('https://site.example.com', $this->CuStaticService->getBaseUrl(''));
    }

    /**
     * copyDirectory（PHPコピー）は増分方式で、変更のないファイルをスキップする
     *
     * 差分出力のアップロードファイル同期（syncUploadFiles）が毎回全ファイルを
     * コピーし直さないことの検証。
     */
    public function testCopyDirectoryIncremental(): void
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'custatic_copy_' . getmypid();
        $src = $base . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $dst = $base . DIRECTORY_SEPARATOR . 'dst' . DIRECTORY_SEPARATOR;
        mkdir($src . 'sub', 0777, true);
        file_put_contents($src . 'a.jpg', 'AAA');
        file_put_contents($src . 'sub' . DIRECTORY_SEPARATOR . 'b.jpg', 'BBB');

        try {
            // 初回は全ファイルコピー
            $copied = $this->execPrivateMethod($this->CuStaticService, 'copyDirectory', [$src, $dst]);
            $this->assertSame(2, $copied);
            $this->assertSame('AAA', file_get_contents($dst . 'a.jpg'));
            $this->assertSame('BBB', file_get_contents($dst . 'sub' . DIRECTORY_SEPARATOR . 'b.jpg'));

            // 変更なしの再実行は全てスキップ
            $copied = $this->execPrivateMethod($this->CuStaticService, 'copyDirectory', [$src, $dst]);
            $this->assertSame(0, $copied);

            // コピー元の更新（サイズ変更）は再コピーされる
            file_put_contents($src . 'a.jpg', 'AAAA');
            $copied = $this->execPrivateMethod($this->CuStaticService, 'copyDirectory', [$src, $dst]);
            $this->assertSame(1, $copied);
            $this->assertSame('AAAA', file_get_contents($dst . 'a.jpg'));

            // 同一サイズでも mtime が新しければ再コピーされる（再アップロード相当）
            file_put_contents($src . 'sub' . DIRECTORY_SEPARATOR . 'b.jpg', 'CCC');
            touch($src . 'sub' . DIRECTORY_SEPARATOR . 'b.jpg', time() + 10);
            $copied = $this->execPrivateMethod($this->CuStaticService, 'copyDirectory', [$src, $dst]);
            $this->assertSame(1, $copied);
            $this->assertSame('CCC', file_get_contents($dst . 'sub' . DIRECTORY_SEPARATOR . 'b.jpg'));
        } finally {
            $this->removeDir($base);
        }
    }

    /**
     * webroot 直下のファイル・フォルダをコピーし、コピーしないものは飛ばす
     */
    public function testCopyRootFiles(): void
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'custatic_root_' . getmypid();
        $www = $base . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR;
        $out = $www . 'static_out' . DIRECTORY_SEPARATOR;
        mkdir($www . 'pdf', 0777, true);
        mkdir($www . 'css', 0777, true);
        mkdir($www . 'bc_blog', 0777, true);
        mkdir($www . 'old', 0777, true);
        mkdir($base . DIRECTORY_SEPARATOR . 'assets', 0777, true);
        mkdir($out, 0777, true);
        file_put_contents($www . 'favicon.ico', 'ICO');
        file_put_contents($www . 'robots.txt', 'User-agent: *');
        file_put_contents($www . 'pdf' . DIRECTORY_SEPARATOR . 'a.pdf', 'PDF');
        file_put_contents($www . 'index.php', '<?php');
        file_put_contents($www . '.htaccess', 'RewriteEngine On');
        file_put_contents($www . 'web.config', '<configuration/>');
        file_put_contents($www . 'css' . DIRECTORY_SEPARATOR . 'style.css', 'body{}');
        file_put_contents($www . 'bc_blog' . DIRECTORY_SEPARATOR . 'x.js', 'x');
        file_put_contents($www . 'old' . DIRECTORY_SEPARATOR . 'b.txt', 'B');
        symlink($base . DIRECTORY_SEPARATOR . 'assets', $www . 'cu_static');

        try {
            Configure::write('CuStatic.rootExclude', ['old']);
            $copied = $this->CuStaticService->copyRootFiles($www, $out);
            sort($copied);
            $this->assertSame(['favicon.ico', 'pdf', 'robots.txt'], $copied);
            $this->assertSame('ICO', file_get_contents($out . 'favicon.ico'));
            $this->assertSame('PDF', file_get_contents($out . 'pdf' . DIRECTORY_SEPARATOR . 'a.pdf'));
            foreach (['index.php', '.htaccess', 'web.config', 'css', 'bc_blog', 'old', 'cu_static', 'static_out'] as $name) {
                $this->assertFileDoesNotExist($out . $name, $name);
            }
        } finally {
            Configure::delete('CuStatic.rootExclude');
            unlink($www . 'cu_static');
            $this->removeDir($base);
        }
    }

    /**
     * テスト用の一時ディレクトリを再帰削除する
     */
    private function removeDir(string $path): void
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
