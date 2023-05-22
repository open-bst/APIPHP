<?php

use core\Api;
use core\Cache;
use core\Log;

/*
  APIPHP开源框架

  ©2023 APIPHP.com

  框架版本号：1.0.0
*/
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}
const _VERSION = '1.0.0';
define('_TIME', microtime(true));
define('_ROOT', str_replace(['\\', '//'], '/', dirname(__FILE__)));
const E_FATAL = E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_PARSE;
$_SERVER['APIPHP'] = ['Config' => [], 'Log' => [], 'Option' => [], 'Runtime' => [], 'URI' => ''];

require(_ROOT . '/config/core/Initial.php');
require(_ROOT . '/lib/core/Initial.php');

date_default_timezone_set($_SERVER['APIPHP']['Config']['core\Initial']['timeZone']);

define('_DEBUG', $_SERVER['APIPHP']['Config']['core\Initial']['debug']);
register_shutdown_function(['core\Initial', 'fatalErr']);
set_error_handler(['core\Initial', 'sysErr'], E_ALL | E_STRICT);
spl_autoload_register(['core\Initial', 'autoload']);

\core\Initial::debug();
ob_start();
\core\Initial::route();

if (!empty($_SERVER['APIPHP']['Log'])) {
    Log::output();
}