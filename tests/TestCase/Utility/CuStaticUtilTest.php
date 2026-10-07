<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Test\TestCase\Utility;

use BaserCore\TestSuite\BcTestCase;
use CuStatic\Utility\CuStaticUtil;

/**
 * CuStaticUtilTest
 *
 * 破壊的削除の安全判定（unsafeReason）の純粋ロジックを検証する。
 */
class CuStaticUtilTest extends BcTestCase
{

    /**
     * 空文字・空白のみは必須バリデーションへ委ねるため null（安全扱い）
     */
    public function testUnsafeReasonReturnsNullForEmpty(): void
    {
        $this->assertNull(CuStaticUtil::unsafeReason(''));
        $this->assertNull(CuStaticUtil::unsafeReason('   '));
    }

    /**
     * 実在しないパスは削除対象にならないため null（安全扱い）
     */
    public function testUnsafeReasonReturnsNullForNonExistentPath(): void
    {
        $this->assertNull(CuStaticUtil::unsafeReason('/no/such/custatic/path/xyzzy'));
    }

    /**
     * アプリの重要ディレクトリ（webroot・config）は拒否
     */
    public function testUnsafeReasonRejectsProtectedDirectories(): void
    {
        $this->assertNotNull(CuStaticUtil::unsafeReason(WWW_ROOT));
        $this->assertNotNull(CuStaticUtil::unsafeReason(CONFIG));
    }

    /**
     * アプリルート自身は保護対象ディレクトリとして拒否
     */
    public function testUnsafeReasonRejectsAppRoot(): void
    {
        $reason = CuStaticUtil::unsafeReason(ROOT);
        $this->assertNotNull($reason);
        $this->assertStringContainsString('重要ディレクトリ', $reason);
    }

    /**
     * アプリルートの祖先（配下にアプリ本体を含む上位）は拒否
     */
    public function testUnsafeReasonRejectsAncestorOfAppRoot(): void
    {
        $parent = dirname(rtrim(ROOT, DIRECTORY_SEPARATOR));
        $reason = CuStaticUtil::unsafeReason($parent);
        $this->assertNotNull($reason);
        $this->assertStringContainsString('アプリ本体', $reason);
    }

    /**
     * アプリ外の十分に深い実在ディレクトリは安全（null）
     */
    public function testUnsafeReasonAllowsSafeExternalPath(): void
    {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'custatic_safe_' . getmypid();
        @mkdir($dir, 0777, true);
        try {
            $this->assertNull(CuStaticUtil::unsafeReason($dir));
        } finally {
            @rmdir($dir);
        }
    }

    /**
     * 内部リンクの href を静的HTML向けパスへ変換する
     *
     * （CuStaticService から CuStaticUtil へ移設。期待値は移設前のまま＝挙動不変の証明）
     *
     * @dataProvider convertHrefDataProvider
     */
    public function testConvertHref(string $href, string $expected): void
    {
        $this->assertSame($expected, CuStaticUtil::convertHref($href, 'example.com', '/news/current'));
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

    /**
     * メールフォームページの症状再現: <a href> の内部リンクが静的URLへ変換される
     *
     * CuStaticAddonMailForm が自前生成するフォームページ（/contact/）で、
     * `/sample` のような拡張子なしリンクが動的URLのまま残る問題の回帰テスト。
     */
    public function testConvertHtmlLinksOnMailFormPage(): void
    {
        $sourceUrl = 'https://cfadmin.example.com/contact/';
        $html = '<a href="/sample">サンプル</a>'
            . '<a href="https://cfadmin.example.com/sample">絶対URL</a>'
            . '<a href="/contact/">自ページ</a>'
            . '<a href="https://external.example.net/sample">外部</a>'
            . '<a href="mailto:info@example.com">メール</a>'
            . '<form action="/contact/confirm" method="post"></form>';

        $converted = CuStaticUtil::convertHtmlLinks($html, $sourceUrl);

        $this->assertStringContainsString('href="/sample.html"', $converted);
        // 同一ホストの絶対URLもルート相対の静的URLへ
        $this->assertStringContainsString('>絶対URL</a>', $converted);
        $this->assertStringNotContainsString('https://cfadmin.example.com/sample', $converted);
        $this->assertStringContainsString('href="/contact/index.html"', $converted);
        // 外部リンク・mailto は不変
        $this->assertStringContainsString('href="https://external.example.net/sample"', $converted);
        $this->assertStringContainsString('href="mailto:info@example.com"', $converted);
        // <a href> 以外（form action 等）は変更しない（JS の /confirm 判定を壊さない）
        $this->assertStringContainsString('action="/contact/confirm"', $converted);
    }

    /**
     * CLI の PHP バイナリ（php・バージョン付き php8.5/php85）は CLI と判定する
     */
    public function testIsCliPhpBinaryAcceptsCliBinaries(): void
    {
        $this->assertTrue(CuStaticUtil::isCliPhpBinary('/usr/bin/php'));
        $this->assertTrue(CuStaticUtil::isCliPhpBinary('/usr/local/bin/php8.5'));
        $this->assertTrue(CuStaticUtil::isCliPhpBinary('/opt/remi/php85/root/bin/php'));
        $this->assertTrue(CuStaticUtil::isCliPhpBinary('/usr/bin/php85'));
    }

    /**
     * FPM/CGI/Web サーバ等の SAPI バイナリは CLI と判定しない
     */
    public function testIsCliPhpBinaryRejectsNonCliBinaries(): void
    {
        $this->assertFalse(CuStaticUtil::isCliPhpBinary(''));
        $this->assertFalse(CuStaticUtil::isCliPhpBinary('/usr/sbin/php-fpm'));
        $this->assertFalse(CuStaticUtil::isCliPhpBinary('/opt/remi/php85/root/usr/sbin/php-fpm'));
        $this->assertFalse(CuStaticUtil::isCliPhpBinary('/usr/bin/php-cgi'));
        $this->assertFalse(CuStaticUtil::isCliPhpBinary('/usr/sbin/httpd'));
        $this->assertFalse(CuStaticUtil::isCliPhpBinary('/usr/sbin/apache2'));
    }

    /**
     * 設定 CuStatic.phpBinary が指定されていれば最優先で採用される
     */
    public function testGetPhpBinaryPrefersConfiguredValue(): void
    {
        $original = \Cake\Core\Configure::read('CuStatic.phpBinary');
        \Cake\Core\Configure::write('CuStatic.phpBinary', '/opt/remi/php85/root/bin/php');
        try {
            $this->assertSame('/opt/remi/php85/root/bin/php', CuStaticUtil::getPhpBinary());
        } finally {
            \Cake\Core\Configure::write('CuStatic.phpBinary', $original);
        }
    }

    /**
     * 設定未指定でも何らかの非空パスへ解決される（CLI 実行下では PHP_BINARY 自身）
     */
    public function testGetPhpBinaryResolvesWithoutConfig(): void
    {
        $original = \Cake\Core\Configure::read('CuStatic.phpBinary');
        \Cake\Core\Configure::write('CuStatic.phpBinary', null);
        try {
            $resolved = CuStaticUtil::getPhpBinary();
            $this->assertNotSame('', $resolved);
            // テストは CLI（phpunit）で実行されるため、PHP_BINARY がそのまま採用されるはず
            if (CuStaticUtil::isCliPhpBinary(PHP_BINARY)) {
                $this->assertSame(PHP_BINARY, $resolved);
            }
        } finally {
            \Cake\Core\Configure::write('CuStatic.phpBinary', $original);
        }
    }

    /**
     * 取得元URLを公開URLへ置き換える（スキーム違い・プロトコル相対・JSON エスケープ）
     */
    public function testReplaceOrigins(): void
    {
        $from = ['https://admin.example.com'];
        $to = 'https://www.example.com';

        $html = '<link href="https://admin.example.com/news/" rel="canonical">'
            . '<img src="http://admin.example.com/files/a.jpg">'
            . '<img src="//admin.example.com/files/b.jpg">'
            . '<script type="application/ld+json">{"url":"https:\/\/admin.example.com\/about"}</script>'
            . '<a href="https://admin.example.com">top</a>'
            . "<p>https://admin.example.com</p>";
        $expected = '<link href="https://www.example.com/news/" rel="canonical">'
            . '<img src="https://www.example.com/files/a.jpg">'
            . '<img src="//www.example.com/files/b.jpg">'
            . '<script type="application/ld+json">{"url":"https:\/\/www.example.com\/about"}</script>'
            . '<a href="https://www.example.com">top</a>'
            . "<p>https://www.example.com</p>";
        $this->assertSame($expected, CuStaticUtil::replaceOrigins($html, $from, $to));
    }

    /**
     * ホスト名の境界を判定し、別オリジン（サブドメイン延長・別ポート・パス中の文字列）は置き換えない
     */
    public function testReplaceOriginsKeepsOtherOrigins(): void
    {
        $from = ['https://admin.example.com'];
        $to = 'https://www.example.com';
        $html = '<a href="https://admin.example.com.evil.test/">x</a>'
            . '<a href="https://admin.example.com:8080/">y</a>'
            . '<a href="https://sub.admin.example.com/">z</a>'
            . '<a href="https://other.test/?u=admin.example.com/">w</a>';
        $this->assertSame($html, CuStaticUtil::replaceOrigins($html, $from, $to));
    }

    /**
     * ポート付き・サブディレクトリ付きの取得元、公開URL未設定・同一URLの扱い
     */
    public function testReplaceOriginsWithPortAndPath(): void
    {
        $this->assertSame(
            '<a href="https://www.example.com/news/">x</a>',
            CuStaticUtil::replaceOrigins('<a href="http://localhost:8080/news/">x</a>', ['http://localhost:8080'], 'https://www.example.com/')
        );
        // サブディレクトリ設置の取得元は、パスごと公開URLへ置き換える（パスの前方一致だけでは置き換えない）
        $this->assertSame(
            '<a href="https://www.example.com/news/">x</a><a href="https://admin.test/cmsx/">y</a>',
            CuStaticUtil::replaceOrigins('<a href="https://admin.test/cms/news/">x</a><a href="https://admin.test/cmsx/">y</a>', ['https://admin.test/cms'], 'https://www.example.com')
        );
        $html = '<a href="https://admin.test/">x</a>';
        $this->assertSame($html, CuStaticUtil::replaceOrigins($html, ['https://admin.test'], ''));
        $this->assertSame($html, CuStaticUtil::replaceOrigins($html, ['https://admin.test/'], 'https://admin.test'));
    }

    /**
     * canonical・og:url の拡張子なしURLを .html 付きへ変換する（ホストは保持）
     */
    public function testConvertHeadUrls(): void
    {
        $url = 'https://admin.example.com/about';
        $html = '<link href="https://admin.example.com/about" rel="canonical">'
            . '<link rel="canonical" href="https://admin.example.com/news/">'
            . '<meta property="og:url" content="https://admin.example.com/news/archives/2">'
            . '<link rel="stylesheet" href="https://admin.example.com/css/style">';
        $expected = '<link href="https://admin.example.com/about.html" rel="canonical">'
            . '<link rel="canonical" href="https://admin.example.com/news/">'
            . '<meta property="og:url" content="https://admin.example.com/news/archives/2.html">'
            . '<link rel="stylesheet" href="https://admin.example.com/css/style">';
        $this->assertSame($expected, CuStaticUtil::convertHeadUrls($html, $url));
    }

    /**
     * RSS の <link>・<guid> の記事URLを .html 付きへ変換する（enclosure・別ホスト・ディレクトリURLは不変）
     */
    public function testConvertFeedLinks(): void
    {
        $url = 'https://admin.example.com/news/index.rss';
        $xml = '<rss><channel><link>https://admin.example.com/</link>'
            . '<item><link>https://admin.example.com/news/archives/2</link>'
            . '<guid>https://admin.example.com/news/archives/2</guid>'
            . '<enclosure url="https://admin.example.com/files/a.jpg" type="" length=""/></item>'
            . '<item><link>https://other.test/page</link></item></channel></rss>';
        $expected = '<rss><channel><link>https://admin.example.com/</link>'
            . '<item><link>https://admin.example.com/news/archives/2.html</link>'
            . '<guid>https://admin.example.com/news/archives/2.html</guid>'
            . '<enclosure url="https://admin.example.com/files/a.jpg" type="" length=""/></item>'
            . '<item><link>https://other.test/page</link></item></channel></rss>';
        $this->assertSame($expected, CuStaticUtil::convertFeedLinks($xml, $url));
    }

}
