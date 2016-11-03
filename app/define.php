<?php

/** @author Martin Mozos <martinmozos@gmail.com> */

define('DS', DIRECTORY_SEPARATOR);
/** @var string $dir_current */
$dir_current = __DIR__.DS;
/** @var string $dir_parent */
$dir_parent = dirname(__DIR__).DS;
// Define constants
define('CONFIG_DIR', $dir_current.'config');
define('PUBLIC_DIR', $dir_parent.'public');
define('RESOURCE_DIR', $dir_current.'Resources'.DS);
define('ROUTER', $dir_current.'router.php');
//define('SERVER', $dir_current.'server.php');
//define('SERVER_DEV_ADDRESS', '0.0.0.0:8000');
//define('PARAMETERS', parseConfig(CONFIG_DIR, 'parameters'));
//define('MESSAGES', parseConfig(CONFIG_DIR, 'messages'));
//define('ROUTES', parseConfig(CONFIG_DIR, 'routing'));
define('TEMPLATE_FILES_DIR', RESOURCE_DIR.\def::parameters()['template_files_dir']);
define('TEMPLATE_CACHE_DIR', $dir_current.\def::parameters()['template_cache_dir']);
define('TEMPLATE_EXTENSION', \def::parameters()['template_extension']);
define('USER_TABLE', \def::parameters()['user_table']);
define('LOGIN_SLUG', \def::parameters()['login_slug']);
define('DEBUG', in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'/*, '123.456.789.0'*/]) ? true : false);

class def
{
	private static $messages;
	private static $parameters;
	private static $routing;
	private static $loaded = false;

	static function load() {
		if (!static::$loaded) {
			static::$messages = parseConfig(CONFIG_DIR, 'messages');
			static::$parameters = parseConfig(CONFIG_DIR, 'parameters');
			static::$routing = parseConfig(CONFIG_DIR, 'routing');
			static::$loaded = true;
		}
	}

	static function messages() {
		static::load();
		return static::$messages;
	}

	static function parameters() {
		static::load();
		return static::$parameters;
	}

	static function routing() {
		static::load();
		return static::$routing;
	}
}