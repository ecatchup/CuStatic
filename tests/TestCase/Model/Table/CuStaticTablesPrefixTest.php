<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Test\TestCase\Model\Table;

use BaserCore\TestSuite\BcTestCase;
use BaserCore\Utility\BcUtil;
use Cake\ORM\TableRegistry;

/**
 * テーブルクラスが接続設定のテーブルプレフィックスを付けることを確認する
 *
 * マイグレーション（BcMigration）はプレフィックス付きで作成するため、
 * テーブルクラス側も付けないと「Could not describe columns」で失敗する。
 */
class CuStaticTablesPrefixTest extends BcTestCase
{

    /**
     * @dataProvider tablesProvider
     */
    public function testTableNameHasPrefix(string $alias, string $table): void
    {
        $prefix = (string)(BcUtil::getCurrentDbConfig()['prefix'] ?? '');
        $this->assertSame($prefix . $table, TableRegistry::getTableLocator()->get($alias)->getTable());
    }

    public static function tablesProvider(): array
    {
        return [
            ['CuStatic.CuStaticConfigs', 'cu_static_configs'],
            ['CuStatic.CuStaticContents', 'cu_static_contents'],
        ];
    }

}
