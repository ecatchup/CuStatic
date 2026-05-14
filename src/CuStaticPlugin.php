<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic;

use BaserCore\BcPlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use CuStatic\Event\CuStaticEventListener;
use CuStatic\ServiceProvider\CuStaticServiceProvider;

/**
 * Plugin for CuStatic
 */
class CuStaticPlugin extends BcPlugin
{

    /**
     * bootstrap
     *
     * @param PluginApplicationInterface $app
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        EventManager::instance()->on(new CuStaticEventListener());
    }

    /**
     * プラグインをインストールする
     *
     * @param array $options
     * @return bool
     */
    public function install($options = []): bool
    {
        return parent::install($options);
    }

    /**
     * プラグインをアンインストールする
     *
     * @param array $options
     * @return bool
     */
    public function uninstall($options = []): bool
    {
        return parent::uninstall($options);
    }

    /**
     * サービスプロバイダを登録する
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->addServiceProvider(new CuStaticServiceProvider());
    }

}
