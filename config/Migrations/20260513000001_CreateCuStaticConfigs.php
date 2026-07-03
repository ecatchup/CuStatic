<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreateCuStaticConfigs extends BcMigration
{
    /**
     * Up Method.
     */
    public function up(): void
    {
        $this->table('cu_static_configs', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('export_path', 'string', [
                'comment' => '出力先フォルダパス',
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('base_url', 'string', [
                'comment' => 'ベースURL（空時はサイト設定を使用）',
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('rsync_command', 'string', [
                'comment' => 'rsyncコマンド（空時はFolderコピー）',
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('target_config', 'text', [
                'comment' => '出力対象設定（JSON: サイト×コンテンツ種別）',
                'default' => null,
                'null' => true,
            ])
            ->addColumn('status', 'boolean', [
                'comment' => '実行中フラグ',
                'default' => false,
                'null' => false,
            ])
            ->addColumn('progress', 'integer', [
                'comment' => '進捗カウント',
                'default' => 0,
                'null' => false,
            ])
            ->addColumn('progress_max', 'integer', [
                'comment' => '進捗最大値',
                'default' => 0,
                'null' => false,
            ])
            ->addColumn('started', 'datetime', [
                'comment' => '書き出し開始時刻',
                'default' => null,
                'null' => true,
            ])
            ->addColumn('finished', 'datetime', [
                'comment' => '書き出し完了時刻',
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->create();
    }

    /**
     * Down Method.
     */
    public function down(): void
    {
        $this->table('cu_static_configs')->drop()->save();
    }
}
