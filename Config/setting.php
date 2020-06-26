<?php
/**
 * [Config] CuStatic
 *
 */
define('LOG_CUSTATIC', 'cu_static');

CakeLog::config(
	'cu_static',
	[
		'engine' => 'FileLog',
		'types' => ['cu_static'],
		'file' => 'cu_static',
	]
);

$config['CuStatic'] = [
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
		'CuStatic' => [
			'title' => __d('baser', '静的コンテンツ書出'),
			'type' => 'cu_static',
			'icon' => 'bca-icon--cu_static',
			'menus' => [
				'CuStatic' => [
					'title' => __d('baser', '静的コンテンツ書出'),
					'url' => [
						'admin' => true,
						'plugin' => 'cu_static',
						'controller' => 'cu_statics',
						'action' => 'index'
					],
				],
				'CuStaticConfig' => [
					'title' => __d('baser', 'オプション設定'),
					'url' => [
						'admin' => true,
						'plugin' => 'cu_static',
						'controller' => 'cu_statics',
						'action' => 'config'
					],
				],
			],
		],
	],
];

$config['BcApp.adminNavi.CuStatic'] = [
	'name' => __d('baser', '静的コンテンツ書出プラグイン'),
	'contents' => [
		[
			'name' => __d('baser', '静的コンテンツ書出'),
			'url' => [
				'admin' => true,
				'plugin' => 'cu_static',
				'controller' => 'cu_statics',
				'action' => 'index',
			],
		],
	],
];
