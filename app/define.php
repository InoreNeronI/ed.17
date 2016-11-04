<?php

/** @author Martin Mozos <martinmozos@gmail.com> */

/** @var string $root_dir */
$root_dir = dirname(__DIR__);

// Define constants
define('CONFIG_DIR', __DIR__.'/config');
define('PUBLIC_DIR', $root_dir.'/public');
define('ROUTER', __DIR__.'/router.php');
//define('SERVER', __DIR__.'/server.php');
//define('SERVER_DEV_ADDRESS', '0.0.0.0:8000');
define('TEMPLATE_FILES_DIR', __DIR__.\def::parameters()['template_files_dir']);
define('TEMPLATE_CACHE_DIR', __DIR__.\def::parameters()['template_cache_dir']);
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
			echo 'va';
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