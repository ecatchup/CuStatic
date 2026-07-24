<?php
/**
 * CuStatic Plugin setting
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

use Cake\Utility\Hash;

$config = [
    'CuStatic' => [
        // 出力対象のコンテンツ種別
        'types' => [
            'Page',
            'ContentFolder',
            'BlogContent',
            'CustomContent',
        ],
        // 出力対象のプラグイン（webroot コピー対象）
        'plugins' => [
            'BcBlog',
            'BcCustomContent',
            'BcFront',
        ],
        // デフォルト並列ワーカー数
        'defaultWorkers' => 4,
        // HTML取得の最大試行回数（5xx・接続エラー時にリトライ。1でリトライなし）
        'httpMaxAttempts' => 3,
        // ブログ投稿集計時のチャンク件数（大量投稿時のメモリ抑制。1件ずつ全件ロードしない）
        'chunkSize' => 1000,
        // CRON による差分出力を使用するか
        // true の場合: 管理画面に「定期実行出力（CRON）設定」が表示され、
        // コンテンツ更新時に差分キューが蓄積されます。
        'cronEnabled' => false,
        // 実行ロックの有効期限（秒）。実行中(status=1)でも開始からこの秒数を超えると
        // 異常終了で取り残された stale ロックとみなし、次回実行が奪取して再開します。
        'lockTimeout' => 3600,
        // アップロードファイルURLの乱数クエリ（?123456789）を除去する。
        // BcUploadHelper がレンダリングごとに '?' . rand() を付与するため、
        // 除去しないと内容が同じでも毎回HTMLが変わり、git/rsync の差分ノイズになる。
        'normalizeUploadQuery' => true,
        // ブログコメントの投稿フォームを静的HTMLから除去する。
        // CSRFトークン・captcha はセッション前提のため静的サイトでは送信できず、
        // かつレンダリングごとに値が変わり差分ノイズになる（コメント一覧の表示は残る）。
        'removeBlogCommentForm' => true,
    ],
    'BcApp' => [
        'adminNavigation' => [
            'Contents' => [
                'CuStatics' => [
                    'title' => '静的HTML出力',
                    'type' => 'cu_static',
                    'icon' => 'bca-icon--file',
                    'menus' => [
                        'CuStatics' => [
                            'title' => '静的HTML出力',
                            'url' => [
                                'prefix' => 'Admin',
                                'plugin' => 'CuStatic',
                                'controller' => 'CuStatics',
                                'action' => 'index',
                            ],
                        ],
                        'CuStaticConfigs' => [
                            'title' => 'オプション設定',
                            'url' => [
                                'prefix' => 'Admin',
                                'plugin' => 'CuStatic',
                                'controller' => 'CuStatics',
                                'action' => 'config',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

if (file_exists(__DIR__ . DS . 'setting_customize.php')) {
    $customize_config = [];
    include __DIR__ . DS . 'setting_customize.php';
    $config = Hash::merge($config, $customize_config);
}

return $config;
