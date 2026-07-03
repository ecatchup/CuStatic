<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Model\Table;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * CuStaticConfigsTable
 *
 * 静的HTML出力プラグインの設定を管理する。
 * 旧版のKV形式（cu_static_configs）をカラム型に変更。
 */
class CuStaticConfigsTable extends Table
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
        $this->setTable('cu_static_configs');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    /**
     * バリデーション設定
     *
     * @param Validator $validator
     * @return Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('export_path', 'create')
            ->notEmptyString('export_path', '出力先フォルダを入力してください。')
            // 全件モードは出力先を丸ごと削除するため、アプリ本体・webroot 等の
            // 重要ディレクトリやその祖先を保存段階で弾く（出力時の検証と同一基準）。
            ->add('export_path', 'safePath', [
                'rule' => function ($value) {
                    return \CuStatic\Utility\CuStaticUtil::unsafeReason((string)$value) ?? true;
                },
            ]);

        $validator
            ->allowEmptyString('base_url')
            ->url('base_url', 'URLの形式が正しくありません。');

        return $validator;
    }

    /**
     * 設定を1件取得する（常に1レコードのみ）
     *
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function getConfig()
    {
        return $this->find()->first();
    }

    /**
     * 保存前に target_config を JSON 文字列へ正規化する
     *
     * @param EventInterface $event
     * @param ArrayObject $data
     * @param ArrayObject $options
     * @return void
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        if (!$data->offsetExists('target_config')) {
            return;
        }
        if (!is_array($data['target_config'])) {
            return;
        }
        $data['target_config'] = $data['target_config']
            ? json_encode($data['target_config'], JSON_UNESCAPED_UNICODE)
            : '{}';
    }

    /**
     * 進捗を更新する
     *
     * @param int $id レコードID
     * @param int $progress 現在の進捗
     * @param int $progressMax 進捗最大値
     * @return bool
     */
    public function updateProgress(int $id, int $progress, int $progressMax): bool
    {
        return (bool) $this->updateAll(
            ['progress' => $progress, 'progress_max' => $progressMax],
            ['id' => $id]
        );
    }

    /**
     * 実行フラグを設定する
     *
     * 開始時（$status=true）は開始時刻を記録し完了時刻をクリアする。
     * 終了時（$status=false）は完了時刻を記録する。
     *
     * @param int $id レコードID
     * @param bool $status 実行中フラグ
     * @return bool
     */
    public function updateStatus(int $id, bool $status): bool
    {
        $now = new \Cake\I18n\DateTime();
        $fields = ['status' => $status];
        if ($status) {
            $fields['started'] = $now;
            $fields['finished'] = null;
        } else {
            $fields['finished'] = $now;
        }

        return (bool) $this->updateAll($fields, ['id' => $id]);
    }

}
