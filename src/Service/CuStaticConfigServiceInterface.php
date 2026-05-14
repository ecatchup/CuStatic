<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Service;

/**
 * CuStaticConfigServiceInterface
 */
interface CuStaticConfigServiceInterface
{

    /**
     * 設定を取得する
     *
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function getConfig();

    /**
     * 設定を保存する
     *
     * 成功時は保存済みエンティティ、失敗時はバリデーションエラーを保持したエンティティを返す（false は返さない）。
     *
     * @param array $data
     * @return \Cake\Datasource\EntityInterface
     */
    public function saveConfig(array $data);

    /**
     * 進捗を更新する
     *
     * @param int $id
     * @param int $progress
     * @param int $progressMax
     * @return bool
     */
    public function updateProgress(int $id, int $progress, int $progressMax): bool;

    /**
     * 実行フラグをリセットする
     *
     * @param int $id
     * @return bool
     */
    public function resetStatus(int $id): bool;

}
