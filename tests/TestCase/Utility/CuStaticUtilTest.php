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

}
