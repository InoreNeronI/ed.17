<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
// Define constants
define('CONFIG_DIR', __DIR__ . '/config');
define('PUBLIC_DIR', dirname(__DIR__) . '/public');
define('RESOURCE_DIR', __DIR__ . '/Resources');
define('TEMPLATE_CONFIG_DIR', RESOURCE_DIR . '/map');
define('TEMPLATE_CACHE_DIR', __DIR__ . \def::parameters()['cache_dir'] . '/twig');
define('TEMPLATE_EXTENSION', 'html.twig');
define('TEMPLATE_FILES_DIR', RESOURCE_DIR . '/view');
define('TEMPLATE_PUBLIC_DIR', RESOURCE_DIR . '/public');
define('TEMPLATE_MESSAGES_DIR', RESOURCE_DIR . '/translation');
define('USER_TABLE', \def::parameters()['user_table']);
define('DEBUG', in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'/*, '123.456.789.0'*/]) ? true : false);
/*define('DEV_ROUTER', __DIR__ . '/router.php');
define('DEV_SERVER', __DIR__ . '/server.php');
define('DEV_SERVER_ADDRESS', '0.0.0.0:8000');*/

class def
{
    private static $accessCodes;
    private static $dbTables;
    private static $langCodes;
    private static $langISOCodes;
    private static $parameters;
    private static $periods;
    private static $routing;
    private static $loaded = false;

    public static function load()
    {
        if (!static::$loaded) {
            static::$accessCodes = parseConfig(TEMPLATE_CONFIG_DIR, 'db_access');
            static::$dbTables = parseConfig(TEMPLATE_CONFIG_DIR, 'db_tables');
            static::$langCodes = parseConfig(TEMPLATE_CONFIG_DIR, 'languages');
            static::$langISOCodes = array_unique(array_values(static::$langCodes));
            static::$parameters = parseConfig(CONFIG_DIR, 'parameters');
            static::$periods = parseConfig(TEMPLATE_CONFIG_DIR, 'periods');
            static::$routing = parseConfig(CONFIG_DIR, 'routing');
            static::$loaded = true;
        }
    }
    public static function accessCodes()
    {
        static::load();

        return static::$accessCodes;
    }
    public static function dbTables()
    {
        static::load();

        return static::$dbTables;
    }
    public static function langCodes()
    {
        static::load();

        return static::$langCodes;
    }
    public static function langISOCodes()
    {
        static::load();

        return static::$langISOCodes;
    }
    public static function parameters()
    {
        static::load();

        return static::$parameters;
    }
    public static function periods()
    {
        static::load();

        return static::$periods;
    }
    public static function routing()
    {
        static::load();

        return static::$routing;
    }
    public static function translations($view = 'layout')
    {
        static::load();

        return parseConfig(TEMPLATE_MESSAGES_DIR, $view);
    }
}
