<?php
/**
 * CuStatic Plugin bootstrap
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

use Cake\Log\Log;

// CuStatic 専用ログのスコープ名
// （レベルではなくスコープで運用し、cu_static.log には CuStatic のログのみを書き出す）
if (!defined('LOG_CU_STATIC')) {
    define('LOG_CU_STATIC', 'cu_static');
}

// CuStatic 専用ログエンジン（cu_static スコープのメッセージのみ cu_static.log に書き出す）
if (!in_array('cu_static', Log::configured(), true)) {
    Log::setConfig('cu_static', [
        'className' => 'File',
        'path'      => LOGS,
        'file'      => 'cu_static',
        'levels'    => ['notice', 'info', 'debug', 'warning', 'error'],
        'scopes'    => ['cu_static'],
    ]);
}

