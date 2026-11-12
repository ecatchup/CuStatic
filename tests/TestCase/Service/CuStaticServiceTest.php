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
use BaserCore\Test\Factory\UserFactory;
use BaserCore\Test\Factory\UserGroupFactory;
use BaserCore\Test\Factory\UsersUserGroupFactory;
use BcBlog\Test\Factory\BlogCategoryFactory;
use BcBlog\Test\Factory\BlogTagFactory;
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
        // ログの scope（プラグインの config/bootstrap.php で定義。テストでは読まれない）
        if (!defined('LOG_CU_STATIC')) {
            define('LOG_CU_STATIC', 'cu_static');
        }
        $this->CuStaticService = new CuStaticService();
    }

    public function tearDown(): void
    {
        unset($this->CuStaticService);
        parent::tearDown();
    }

    /**
     * copyAssets は、フォルダごとにコピーを始める前にもログを出す（管理画面のログ表示で進み具合が分かるように）
     */
    public function testCopyAssetsLogsBeforeCopy(): void
    {
        $tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'custatic_copylog_' . getmypid() . DIRECTORY_SEPARATOR;
        $www = $tmp . 'webroot' . DIRECTORY_SEPARATOR;
        $out = $tmp . 'export' . DIRECTORY_SEPARATOR;
        mkdir($www . 'files', 0777, true);
        file_put_contents($www . 'files' . DIRECTORY_SEPARATOR . 'a.txt', 'a');
        $backup = [
            'www_root' => Configure::read('App.www_root'),
            'plugins' => Configure::read('CuStatic.plugins'),
            'copyRootFiles' => Configure::read('CuStatic.copyRootFiles'),
            'rsyncCommand' => Configure::read('CuStatic.rsyncCommand'),
        ];
        Configure::write('App.www_root', $www);
        Configure::write('CuStatic.plugins', []);
        Configure::write('CuStatic.copyRootFiles', false);
        Configure::write('CuStatic.rsyncCommand', '');
        \Cake\Log\Log::setConfig('cu_static_test_array', [
            'className' => 'Array',
            'levels' => ['info'],
            'scopes' => [LOG_CU_STATIC],
        ]);
        try {
            $this->CuStaticService->copyAssets($out, []);
            $logs = \Cake\Log\Log::engine('cu_static_test_array')->read();
            $src = $www . 'files' . DIRECTORY_SEPARATOR;
            $before = null;
            $after = null;
            foreach ($logs as $i => $line) {
                if ($before === null && str_contains($line, '[copyAssets] コピーしています… ' . $src)) {
                    $before = $i;
                }
                if ($after === null && preg_match('/\[copyAssets\] ' . preg_quote($src, '/') . '（\d+ 秒）/u', $line)) {
                    $after = $i;
                }
            }
            $this->assertNotNull($before, 'コピー前のログが無い');
            $this->assertNotNull($after, 'コピー後のログが無い');
            $this->assertLessThan($after, $before);
            $this->assertFileExists($out . 'files' . DIRECTORY_SEPARATOR . 'a.txt');
        } finally {
            \Cake\Log\Log::drop('cu_static_test_array');
            Configure::write('App.www_root', $backup['www_root']);
            Configure::write('CuStatic.plugins', $backup['plugins']);
            Configure::write('CuStatic.copyRootFiles', $backup['copyRootFiles']);
            Configure::write('CuStatic.rsyncCommand', $backup['rsyncCommand']);
            exec('rm -rf ' . escapeshellarg(rtrim($tmp, DIRECTORY_SEPARATOR)));
        }
    }

    /**
     * 設定 redirects を _redirects の形式で書き出す。空なら書き出さない
     */
    public function testWriteRedirects(): void
    {
        $out = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'custatic_redirects_' . getmypid() . DIRECTORY_SEPARATOR;
        mkdir($out, 0777, true);
        try {
            Configure::write('CuStatic.redirects', []);
            $this->assertSame(0, $this->CuStaticService->writeRedirects($out));
            $this->assertFileDoesNotExist($out . '_redirects');

            Configure::write('CuStatic.redirects', [
                '/license/' => '/about/license',
                '/old/index.html' => '/new/',
                '/bad path' => '/x',
            ]);
            $this->assertSame(2, $this->CuStaticService->writeRedirects($out));
            $this->assertSame("/license/ /about/license 301\n/old/index.html /new/ 301\n", file_get_contents($out . '_redirects'));
        } finally {
            Configure::delete('CuStatic.redirects');
            $this->removeDir($out);
        }
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
     * 差分のキューのコンテンツリンクは、固定ページと同じ名前で書き出す
     */
    public function testAddDiffJobsForContentLink(): void
    {
        $jobs = [];
        $item = (object) ['type' => 'ContentLink', 'url' => '/service/github', 'site_id' => 0, 'entity_id' => 1];
        $this->execPrivateMethod($this->CuStaticService, 'addDiffJobsForItem', [$item, [], '/tmp/export/', 'https://example.com', &$jobs]);
        $jobs = array_values($jobs);
        $this->assertCount(1, $jobs);
        $this->assertSame('https://example.com/service/github', $jobs[0]['url']);
        $this->assertSame('/tmp/export/service/github.html', $jobs[0]['path']);
    }

    /**
     * 削除されたコンテンツリンクは、固定ページと同じ名前のファイルを消す
     */
    public function testStaticPathsForContentLink(): void
    {
        $item = (object) ['type' => 'ContentLink', 'url' => '/service/github'];
        $paths = $this->execPrivateMethod($this->CuStaticService, 'staticPathsForItem', [$item, '/tmp/export/']);
        $this->assertSame(['/tmp/export/service/github.html'], $paths);
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
    /**
     * カテゴリ別件数のロールアップ（記事が 0 件のカテゴリを含めるか）
     */
    public function testRollupCategoryCountsIncludeEmpty(): void
    {
        $this->loadPlugins(['BcBlog']);
        BlogCategoryFactory::make(['id' => 101, 'blog_content_id' => 1, 'name' => 'has-posts', 'lft' => 1, 'rght' => 2])->persist();
        BlogCategoryFactory::make(['id' => 102, 'blog_content_id' => 1, 'name' => 'empty', 'lft' => 3, 'rght' => 4])->persist();

        // 既定（含めない）: 記事のあるカテゴリだけ
        $result = $this->execPrivateMethod($this->CuStaticService, 'rollupCategoryCounts', [1, [101 => 2]]);
        $this->assertSame(['has-posts' => 2], $result);

        // 含める: 記事が 0 件のカテゴリも件数 0 で返る
        $result = $this->execPrivateMethod($this->CuStaticService, 'rollupCategoryCounts', [1, [101 => 2], true]);
        $this->assertSame(['has-posts' => 2, 'empty' => 0], $result);
    }

    /**
     * 記事が 0 件のタグ・著者（exportEmptyArchives の tag・author で足すもの）
     */
    public function testEmptyTagAndAuthorCounts(): void
    {
        $this->loadPlugins(['BcBlog']);
        BlogTagFactory::make(['id' => 201, 'name' => 'used'])->persist();
        BlogTagFactory::make(['id' => 202, 'name' => 'unused'])->persist();
        UserFactory::make(['id' => 301, 'status' => true])->persist();
        UserFactory::make(['id' => 302, 'status' => true])->persist();
        UserFactory::make(['id' => 303, 'status' => false])->persist();
        UserFactory::make(['id' => 304, 'status' => true])->persist();
        // 利用できるユーザーは、どれかのユーザーグループに属する（304 は属さない）
        UserGroupFactory::make(['id' => 1, 'name' => 'admins'])->persist();
        foreach ([301, 302, 303] as $userId) {
            UsersUserGroupFactory::make(['user_id' => $userId, 'user_group_id' => 1])->persist();
        }

        // 記事のあるタグは除き、ほかのタグを件数 0 で返す
        $result = $this->execPrivateMethod($this->CuStaticService, 'emptyTagCounts', [['used' => 3]]);
        $this->assertSame(['unused' => 0], $result);

        // 記事のある著者は除き、利用できるユーザー（status が有効）を件数 0 で返す
        $result = $this->execPrivateMethod($this->CuStaticService, 'emptyAuthorCounts', [[301 => 2]]);
        $this->assertArrayHasKey(302, $result);
        $this->assertArrayNotHasKey(301, $result);
        $this->assertArrayNotHasKey(303, $result);
        $this->assertArrayNotHasKey(304, $result);
        $this->assertSame(0, $result[302]);
    }

    /**
     * 設定 exportEmptyArchives の判定
     */
    public function testExportsEmptyArchive(): void
    {
        Configure::write('CuStatic.exportEmptyArchives', ['category', 'tag']);
        $this->assertTrue($this->execPrivateMethod($this->CuStaticService, 'exportsEmptyArchive', ['category']));
        $this->assertTrue($this->execPrivateMethod($this->CuStaticService, 'exportsEmptyArchive', ['tag']));
        $this->assertFalse($this->execPrivateMethod($this->CuStaticService, 'exportsEmptyArchive', ['author']));
        Configure::write('CuStatic.exportEmptyArchives', []);
        $this->assertFalse($this->execPrivateMethod($this->CuStaticService, 'exportsEmptyArchive', ['category']));
    }

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
