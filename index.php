<?php

use core\Api;
use core\Cache;
use core\Log;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/

const _VERSION = '1.0.0';
define('_TIME', microtime(true));
define('_ROOT', str_replace(['\\', '//'], '/', dirname(__FILE__)));
const E_FATAL = E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_PARSE;
$_SERVER['APIPHP'] = ['Config' => [], 'Log' => [], 'Option' => [], 'Runtime' => [], 'URI' => ''];

require(_ROOT . '/config/core/Initial.php');
require(_ROOT . '/lib/core/Initial.php');

define('_DEBUG', \core\Initial::getConfig('debug'));

register_shutdown_function(['core\Initial','fatalErr']);
set_error_handler(['core\Initial','sysErr'], E_ALL | E_STRICT);
date_default_timezone_set(\core\Initial::getConfig('timeZone'));
\core\Initial::getConfig('timeLimit') !== false ? set_time_limit(\core\Initial::getConfig('timeLimit'));

spl_autoload_register(['core\Initial','autoload']);

if (!_DEBUG) {
    error_reporting(0);
} else {
    header('Cache-Control: no-cache,must-revalidate');
    header('Pragma: no-cache');
    header("Expires: -1");
    header('Last-Modified: Thu, 01 Jan 1970 00:00:00 GMT');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    die('HELLO.');
}

//缓冲区控制开启
ob_start();

//路由
\core\Initial::route();

//输出日志
empty($_SERVER['APIPHP']['Log']) ? Log::output();