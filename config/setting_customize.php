<?php
/**
 * CuStatic カスタマイズ設定
 *
 * このファイルを setting_customize.php にリネームして使用してください。
 * setting.php の値をここで上書きできます。
 * setting_customize.php は .gitignore に含まれているため、
 * リポジトリにコミットされません。
 */

$customize_config = [
    'CuStatic' => [
        // デフォルト並列ワーカー数
        'defaultWorkers' => 8,
        // CRON による差分出力を有効化するか
        'cronEnabled' => true,

        // 出力対象のコンテンツ種別
        // 'types' => [
        //     'Page',
        //     'ContentFolder',
        //     'BlogContent',
        // ],

        // 出力対象のプラグイン（webroot コピー対象）
        // 'plugins' => [
        //     'BcBlog',
        //     'BcFront',
        // ],
    ],

    // 管理画面メニューのカスタマイズ例
    // 'BcApp' => [
    //     'adminNavigation' => [
    //         'Contents' => [
    //             'CuStatics' => null,
    //         ],
    //     ],
    // ],
];
