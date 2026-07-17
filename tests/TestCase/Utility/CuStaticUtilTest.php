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

}
