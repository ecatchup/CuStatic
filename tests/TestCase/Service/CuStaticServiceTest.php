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
 * I/O を伴わない純粋ロジック（ベースURL決定・href の静的パス変換）を検証する。
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
     * 内部リンクの href を静的HTML向けパスへ変換する
     *
     * @dataProvider convertHrefDataProvider
     */
    public function testConvertHref(string $href, string $expected): void
    {
        $result = $this->execPrivateMethod(
            $this->CuStaticService,
            'convertHref',
            [$href, 'example.com', '/news/current']
        );
        $this->assertSame($expected, $result);
    }

    public static function convertHrefDataProvider(): array
    {
        return [
            // アンカー・外部スキームはそのまま
            'アンカー' => ['#section', '#section'],
            'mailto' => ['mailto:info@example.com', 'mailto:info@example.com'],
            // 別ホストの絶対URLはそのまま
            '別ホスト' => ['https://other.example.net/foo', 'https://other.example.net/foo'],
            // 同一ホストの絶対URL → 拡張子付与
            '同一ホスト絶対URL' => ['https://example.com/about', '/about.html'],
            // 拡張子なしのルート相対 → .html 付与
            '拡張子なし' => ['/company/access', '/company/access.html'],
            // 末尾スラッシュ → index.html
            '末尾スラッシュ' => ['/company/', '/company/index.html'],
            // 拡張子付き（アセット）はそのまま
            'アセット' => ['/theme/style.css', '/theme/style.css'],
            // 相対リンクは現在パスのディレクトリ基準で解決
            '相対リンク' => ['sub/page', '/news/sub/page.html'],
            // ページネーション page>=2 → /page-N.html
            'ページネーション' => ['/news?page=2', '/news/page-2.html'],
            // page=1 は一覧本体
            'ページ1' => ['/news?page=1', '/news.html'],
            // 日付アーカイブの月はゼロ埋めに正規化
            '日付アーカイブ' => ['/news/archives/date/2026/6', '/news/archives/date/2026/06.html'],
        ];
    }

}
