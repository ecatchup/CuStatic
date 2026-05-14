<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Model\Table;

use Cake\ORM\Table;

/**
 * CuStaticContentsTable
 *
 * 静的HTML出力が必要なコンテンツを差分キューとして管理する。
 * 旧版の meta / controller / action カラムを廃止。
 */
class CuStaticContentsTable extends Table
{

    /**
     * initialize
     *
     * @param array $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('cu_static_contents');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    /**
     * 指定サイトIDのキューを全件削除する
     *
     * @param int|null $siteId null の場合は全サイト
     * @return int 削除件数
     */
    public function clearQueue(?int $siteId = null): int
    {
        $conditions = [];
        if ($siteId !== null) {
            $conditions['site_id'] = $siteId;
        }
        return $this->deleteAll($conditions);
    }

    /**
     * コンテンツをキューに登録する（重複はupsert・後勝ち）
     *
     * `action`（update=再生成 / delete=静的ファイル削除）を含めて保存する。
     * 同一エンティティを再登録する際は先に削除するため、最後の操作の action が残る。
     *
     * @param array $data
     * @return bool
     */
    public function enqueue(array $data): bool
    {
        // action 未指定時は再生成扱い
        if (empty($data['action'])) {
            $data['action'] = 'update';
        }

        // 同一エンティティが既にある場合は削除して再登録
        if (!empty($data['entity_id'])) {
            $this->deleteAll([
                'plugin' => $data['plugin'] ?? null,
                'type' => $data['type'] ?? null,
                'content_id' => $data['content_id'] ?? null,
                'entity_id' => $data['entity_id'],
            ]);
        }

        $entity = $this->newEntity($data);
        return (bool) $this->save($entity);
    }

}
