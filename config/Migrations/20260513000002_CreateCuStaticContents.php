<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreateCuStaticContents extends BcMigration
{
    /**
     * Up Method.
     */
    public function up(): void
    {
        $this->table('cu_static_contents', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('name', 'string', [
                'comment' => 'コンテンツスラッグ名',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('plugin', 'string', [
                'comment' => 'プラグイン名（Blog, Pages など）',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('type', 'string', [
                'comment' => 'コンテンツ種別（Page / ContentFolder / BlogContent / BlogPost / CustomContent / CustomEntry）',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('action', 'string', [
                'comment' => '差分アクション（update=再生成 / delete=静的ファイル削除）',
                'default' => 'update',
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('content_id', 'integer', [
                'comment' => 'コンテンツ管理テーブルのID',
                'default' => null,
                'null' => true,
            ])
            ->addColumn('entity_id', 'integer', [
                'comment' => '各プラグインのエンティティID',
                'default' => null,
                'null' => true,
            ])
            ->addColumn('url', 'text', [
                'comment' => 'コンテンツURL',
                'default' => null,
                'null' => true,
            ])
            ->addColumn('site_id', 'integer', [
                'comment' => 'サイトID（0=メインサイト）',
                'default' => 0,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addIndex(['site_id'])
            ->addIndex(['type'])
            ->addIndex(['content_id', 'entity_id'])
            ->create();
    }

    /**
     * Down Method.
     */
    public function down(): void
    {
        $this->table('cu_static_contents')->drop()->save();
    }
}
