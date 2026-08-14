<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class AddPublicUrlToCuStaticConfigs extends BcMigration
{
    /**
     * Up Method.
     *
     * 静的サイトの公開URL（書き出したHTMLを配信するURL）を追加する。
     * アドオン（静的メールフォーム等）が許可オリジンの既定値として参照する。
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('cu_static_configs')
            ->addColumn('public_url', 'string', [
                'comment' => '静的サイトの公開URL',
                'default' => null,
                'limit' => 255,
                'null' => true,
                'after' => 'base_url',
            ])
            ->update();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('cu_static_configs')
            ->removeColumn('public_url')
            ->update();
    }
}
