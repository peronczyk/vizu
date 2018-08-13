<?php

# ==================================================================================
#
#	VIZU CMS
#	https://github.com/peronczyk/vizu
#
# ==================================================================================


define('VIZU_VERSION', '1.2.2');
define('__ROOT__', __DIR__);


/**
 * Load configuration
 */

if (!file_exists('config-app.php')) {
	die('Configuration file does not exist');
}
require_once 'config-app.php';


/**
 * Load class autoloader
 */

require_once Config::$APP_DIR . 'autoload.php';


/**
 * Start core libraries
 */

$core   = new libs\Core();
$router = new libs\Router();


/**
 * Load theme configuration
 */

$theme_configuration_file = \Config::$THEMES_DIR . \Config::$THEME_NAME . '/config-theme.php';
if (!file_exists($theme_configuration_file)) {
	libs\Core::error('Theme configuration file is missing', __FILE__, __LINE__, debug_backtrace());
}
$theme_config = require_once $theme_configuration_file;


/**
 * Redirects based on configuration and enviroment
 */

if (Config::$REDIRECT_TO_WWW === true) {
	$router->redirectToWww();
}


/**
 * Load database handler library
 * The connection is performed on the occasion of the first query.
 * Connection configuration depends on enviroment - development or production.
 */

$db_config = $core->loadDatabaseConfig();
$db = new libs\Mysqli($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['name']);


/**
 * Start language library and set active language
 * if user is not in installation process
 */

if ($router->getFirstRequest() !== 'install') {
	$lang = new libs\Language($router, $db);
	$lang->set();
	$lang->loadThemeTranslations();
}


/**
 * Load the module based on the page address
 */

$module_to_load = $router->getModuleToLoad();
if ($module_to_load) {
	require_once $module_to_load;
}