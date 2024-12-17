<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

// Define ID for env
$id = 'APP_' . microtime(true) . '_';
define('CURR_APP_ID', $id);

// for debug
error_reporting(E_ALL & ~(E_STRICT | E_NOTICE));

// hide all error messages
// error_reporting(0);

date_default_timezone_set('Asia/Shanghai');

// set internal encoding
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

define('DS', DIRECTORY_SEPARATOR);
define('PS', PATH_SEPARATOR);
define('UNDERLINE', '_');

defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__FILE__)));
defined('BASE_CONFIG_PATH') || define('BASE_CONFIG_PATH', APPLICATION_PATH . DS . 'config');
defined('SYSTEM_PATH') || define('SYSTEM_PATH', APPLICATION_PATH . DS . 'system');
defined('API_PATH') || define('API_PATH', SYSTEM_PATH . DS . 'framework');
defined('APPS_PATH') || define('APPS_PATH', APPLICATION_PATH . DS . 'apps');
defined('DATA_PATH') || define('DATA_PATH', APPLICATION_PATH . DS . 'data');
defined('FILE_PATH') || define('FILE_PATH', DATA_PATH . DS . 'files');
defined('LIBRARY_PATH') || define('LIBRARY_PATH', SYSTEM_PATH . DS . 'library');
define('APP_LOG_PATH', APPLICATION_PATH . DS . 'data' . DS . 'logs');

define('TIMESTAMP', time());

set_include_path(implode(PATH_SEPARATOR, array(API_PATH, APPS_PATH, LIBRARY_PATH, get_include_path())));

require_once 'vendor/autoload.php';
set_error_handler('\\Key\\App::errorHandler');
/**
 * Global object.
 *
 * @global stdClass $CONFIG
 */
global $CONFIG;
if (!isset ($CONFIG)) {
    $CONFIG = new stdClass ();
}

//$loader = new \Composer\Autoload\ClassLoader();
//$CONFIG->classLoader = $loader;
