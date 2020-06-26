<?php
/**
 * [Config] BcStatic
 *
 */
define('LOG_BCSTATIC', 'bc_static');

CakeLog::config(
	'bc_static',
	[
		'engine' => 'FileLog',
		'types' => ['bc_static'],
		'file' => 'bc_static',
	]
);

$config['BcStatic'] = [
	'exportPath' => TMP . 'static' . DS,
	'baseUrl' => '',
	'command' => 'exec.sh > /dev/null 2>&1 &',
	'plugins' => [
		'Blog',
		'BurgerEditor',
	]
];

/**
 * システムナビ
 */
$config['BcApp.adminNavigation'] = [
	'Contents' => [
		'BcStatic' => [
			'title' => __d('baser', '静的コンテンツ書出'),
			'type' => 'bc_static',
			'icon' => 'bca-icon--bc_static',
			'menus' => [
				'BcStatic' => [
					'title' => __d('baser', '静的コンテンツ書出'),
					'url' => [
						'admin' => true,
						'plugin' => 'bc_static',
						'controller' => 'bc_statics',
						'action' => 'index'
					],
				],
				'BcStaticConfig' => [
					'title' => __d('baser', 'オプション設定'),
					'url' => [
						'admin' => true,
						'plugin' => 'bc_static',
						'controller' => 'bc_statics',
						'action' => 'config'
					],
				],
			],
		],
	],
];

$config['BcApp.adminNavi.BcStatic'] = [
	'name' => __d('baser', '静的コンテンツ書出プラグイン'),
	'contents' => [
		[
			'name' => __d('baser', '静的コンテンツ書出'),
			'url' => [
				'admin' => true,
				'plugin' => 'bc_static',
				'controller' => 'bc_statics',
				'action' => 'index',
			],
		],
	],
];
