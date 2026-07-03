<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\ServiceProvider;

use Cake\Core\ServiceProvider;
use CuStatic\Service\CuStaticConfigService;
use CuStatic\Service\CuStaticConfigServiceInterface;
use CuStatic\Service\CuStaticService;
use CuStatic\Service\CuStaticServiceInterface;

/**
 * CuStaticServiceProvider
 */
class CuStaticServiceProvider extends ServiceProvider
{

    /**
     * @var string[]
     */
    protected array $provides = [
        CuStaticServiceInterface::class,
        CuStaticConfigServiceInterface::class,
    ];

    /**
     * @param \Cake\Core\ContainerInterface $container
     * @return void
     */
    public function services($container): void
    {
        $container->defaultToShared(true);
        $container->add(CuStaticServiceInterface::class, CuStaticService::class);
        $container->add(CuStaticConfigServiceInterface::class, CuStaticConfigService::class);
    }

}
