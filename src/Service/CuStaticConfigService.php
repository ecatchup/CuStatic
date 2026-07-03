<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Service;

use Cake\ORM\TableRegistry;

/**
 * CuStaticConfigService
 *
 * 静的HTML出力プラグインの設定を管理するサービス。
 */
class CuStaticConfigService implements CuStaticConfigServiceInterface
{

    /**
     * @var \CuStatic\Model\Table\CuStaticConfigsTable
     */
    protected $CuStaticConfigs;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->CuStaticConfigs = TableRegistry::getTableLocator()->get('CuStatic.CuStaticConfigs');
    }

    /**
     * 設定を取得する
     *
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function getConfig()
    {
        return $this->CuStaticConfigs->getConfig();
    }

    /**
     * 設定を保存する
     *
     * 成功時は保存済みエンティティ、失敗時はバリデーションエラーを保持したエンティティを返す（false は返さない）。
     *
     * @param array $data
     * @return \Cake\Datasource\EntityInterface
     */
    public function saveConfig(array $data)
    {
        $config = $this->CuStaticConfigs->getConfig() ?? $this->CuStaticConfigs->newEmptyEntity();
        $entity = $this->CuStaticConfigs->patchEntity($config, $data);
        return $this->CuStaticConfigs->save($entity) ?: $entity;
    }

    /**
     * 進捗を更新する
     *
     * @param int $id
     * @param int $progress
     * @param int $progressMax
     * @return bool
     */
    public function updateProgress(int $id, int $progress, int $progressMax): bool
    {
        return $this->CuStaticConfigs->updateProgress($id, $progress, $progressMax);
    }

    /**
     * 実行フラグをリセットする
     *
     * @param int $id
     * @return bool
     */
    public function resetStatus(int $id): bool
    {
        return $this->CuStaticConfigs->updateStatus($id, false);
    }

}
