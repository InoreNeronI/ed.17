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
define('PARAMETERS', parseConfig(CONFIG_DIR, 'parameters'));
define('MESSAGES', parseConfig(CONFIG_DIR, 'messages'));
define('ROUTES', parseConfig(CONFIG_DIR, 'routing'));
define('TEMPLATE_FILES_DIR', RESOURCE_DIR.PARAMETERS['template_files_dir']);
define('TEMPLATE_CACHE_DIR', $dir_current.PARAMETERS['template_cache_dir']);
define('TEMPLATE_EXTENSION', PARAMETERS['template_extension']);
define('USER_TABLE', PARAMETERS['user_table']);
define('LOGIN_SLUG', PARAMETERS['login_slug']);
define('DEBUG', in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'/*, '123.456.789.0'*/]) ? true : false);
