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
App::uses('CuStaticUtil', 'CuStatic.Lib');

$config['CuStatic'] = [
	'exportPath' => TMP . 'static' . DS,
	'baseUrl' => '',
	'command' => 'exec.sh %s > /dev/null &',
	'rsyncCommand' => '',
	// 'rsyncCommand' => 'rsync -avh --delete --exclude="admin"',
	'plugins' => [
		'Blog',
		'BurgerEditor',
	],
	'types' => [
		'Page',
		'ContentFolder',
		'BlogContent',
		'BlogPost',
	],
	'mode' => [
		'all' => [
			'title' => '全件書出',
			'prefix' => '',
		],
		// 定期実行書出を利用するときはここのコメントを外してCRONをセットしてください
		// 'diff' => [
		// 	'title' => '定期実行書出（CRON）',
		// 	'prefix' => 'diff_',
		// ],
	],
];

/**
 * システムナビ
 */
$config['BcApp.adminNavigation'] = [
	'Contents' => [
		'CuStatic' => [
			'title' => __d('baser', '静的HTML出力'),
			'type' => 'cu_static',
			'icon' => 'bca-icon--file',
			'menus' => [
				'CuStatic' => [
					'title' => __d('baser', '静的HTML出力'),
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
	'name' => __d('baser', '静的HTML出力プラグイン'),
	'contents' => [
		[
			'name' => __d('baser', '静的HTML出力'),
			'url' => [
				'admin' => true,
				'plugin' => 'cu_static',
				'controller' => 'cu_statics',
				'action' => 'index',
			],
		],
	],
];
