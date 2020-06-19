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
	// 'exportPath' => TMP . 'static' . DS,
	'exportPath' => ROOT  . DS . '..' . DS . 'basercms4-html' . DS,
	'baseUrl' => '',
	'command' => 'exec.sh > /dev/null 2>&1 &',
	'plugins' => [
		'Blog',
		'BurgerEditor',
	]
];
