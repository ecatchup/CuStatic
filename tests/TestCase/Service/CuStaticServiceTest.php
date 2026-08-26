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

}
