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
 * CuStaticServiceConvertContentTest
 *
 * 取得したHTML/RSSの静的出力向け変換（内部URLの .html 化と公開URLへの置き換え）を検証する。
 */
class CuStaticServiceConvertContentTest extends BcTestCase
{

    /**
     * @var CuStaticService
     */
    protected $CuStaticService;

    /**
     * @var array
     */
    protected array $originalEnv = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->CuStaticService = new CuStaticService();
        $this->originalEnv = [
            'siteUrl' => Configure::read('BcEnv.siteUrl'),
            'sslUrl' => Configure::read('BcEnv.sslUrl'),
        ];
        Configure::write('BcEnv.siteUrl', 'https://admin.example.com/');
        Configure::write('BcEnv.sslUrl', '');
    }

    public function tearDown(): void
    {
        Configure::write('BcEnv.siteUrl', $this->originalEnv['siteUrl']);
        Configure::write('BcEnv.sslUrl', $this->originalEnv['sslUrl']);
        Configure::delete('CuStatic.rewritePublicUrl');
        unset($this->CuStaticService);
        parent::tearDown();
    }

    /**
     * 置き換え元は取得元ベースURLとサイトURL（重複・末尾スラッシュは除去）
     */
    public function testGetRewriteFromUrls(): void
    {
        $this->assertSame(
            ['http://localhost:8080', 'https://admin.example.com'],
            $this->CuStaticService->getRewriteFromUrls('http://localhost:8080/')
        );
        $this->assertSame(
            ['https://admin.example.com'],
            $this->CuStaticService->getRewriteFromUrls('https://admin.example.com')
        );
    }

    /**
     * HTML: 内部リンクの .html 化 → canonical の .html 化 → 公開URLへの置き換え が両方効く
     */
    public function testConvertHtml(): void
    {
        $this->CuStaticService->configureUrlRewrite('https://admin.example.com', 'https://www.example.com/');
        $html = '<head><link href="https://admin.example.com/news/archives/2" rel="canonical">'
            . '<meta property="og:image" content="https://admin.example.com/files/a.jpg"></head>'
            . '<body><a href="https://admin.example.com/about">about</a></body>';
        $expected = '<head><link href="https://www.example.com/news/archives/2.html" rel="canonical">'
            . '<meta property="og:image" content="https://www.example.com/files/a.jpg"></head>'
            . '<body><a href="/about.html">about</a></body>';
        $this->assertSame(
            $expected,
            $this->CuStaticService->convertContent($html, 'https://admin.example.com/news/archives/2', '/tmp/x/news/archives/2.html')
        );
    }

    /**
     * RSS: 記事URLの .html 化と公開URLへの置き換え（enclosure の乱数クエリも除去）
     */
    public function testConvertRss(): void
    {
        $this->CuStaticService->configureUrlRewrite('https://admin.example.com', 'https://www.example.com');
        $xml = '<rss><channel><link>https://admin.example.com/</link><item>'
            . '<link>https://admin.example.com/news/archives/2</link>'
            . '<guid>https://admin.example.com/news/archives/2</guid>'
            . '<enclosure url="https://admin.example.com/files/blog/a.jpg?188560746" type="" length=""/>'
            . '</item></channel></rss>';
        $expected = '<rss><channel><link>https://www.example.com/</link><item>'
            . '<link>https://www.example.com/news/archives/2.html</link>'
            . '<guid>https://www.example.com/news/archives/2.html</guid>'
            . '<enclosure url="https://www.example.com/files/blog/a.jpg" type="" length=""/>'
            . '</item></channel></rss>';
        $this->assertSame(
            $expected,
            $this->CuStaticService->convertContent($xml, 'https://admin.example.com/news/index.rss', '/tmp/x/news/index.rss')
        );
    }

    /**
     * 公開URL未設定、または rewritePublicUrl=false では置き換えない（.html 化は行う）
     */
    public function testNoRewriteWithoutPublicUrl(): void
    {
        $html = '<link href="https://admin.example.com/about" rel="canonical">';
        $expected = '<link href="https://admin.example.com/about.html" rel="canonical">';

        $this->CuStaticService->configureUrlRewrite('https://admin.example.com', '');
        $this->assertSame($expected, $this->CuStaticService->convertContent($html, 'https://admin.example.com/about', '/tmp/x/about.html'));

        Configure::write('CuStatic.rewritePublicUrl', false);
        $this->CuStaticService->configureUrlRewrite('https://admin.example.com', 'https://www.example.com');
        $this->assertSame($expected, $this->CuStaticService->convertContent($html, 'https://admin.example.com/about', '/tmp/x/about.html'));
    }

    /**
     * HTML/RSS/XML 以外は変換しない
     */
    public function testOtherExtensionUntouched(): void
    {
        $this->CuStaticService->configureUrlRewrite('https://admin.example.com', 'https://www.example.com');
        $json = '{"url":"https://admin.example.com/about"}';
        $this->assertSame($json, $this->CuStaticService->convertContent($json, 'https://admin.example.com/a.json', '/tmp/x/a.json'));
    }

}
