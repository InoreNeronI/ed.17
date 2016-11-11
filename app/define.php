<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
define('CONFIG_DIR', __DIR__ . '/config');
define('PUBLIC_DIR', dirname(__DIR__) . '/public');
define('TEMPLATE_CACHE_DIR', __DIR__ . \def::parameters()['cache_dir'] . '/twig');
define('TEMPLATE_DATA_DIR', __DIR__ . \def::parameters()['data_dir']);
define('TEMPLATE_EXTENSION', 'html.twig');
define('TEMPLATE_FILES_DIR', __DIR__ . \def::parameters()['template_dir']);
define('TEMPLATE_PUBLIC_DIR', __DIR__ . \def::parameters()['public_dir']);
define('TEMPLATE_MESSAGES_DIR', __DIR__ . \def::parameters()['translation_dir']);
define('USER_TABLE', \def::parameters()['user_table']);
define('DEBUG', in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'/*, '123.456.789.0'*/]) ? true : false);

class def
{
    private static $configLoaded = false;
    private static $dbTables;
    private static $initialized = false;
    private static $langCodes;
    private static $langISOCodes;
    private static $parameters;
    private static $periods;
    private static $routing;
    private static $security;

    private static function init()
    {
        if (!static::$initialized) {
            static::$parameters = parseConfig(CONFIG_DIR, 'parameters');
            static::$routing = parseConfig(CONFIG_DIR, 'routing');
            static::$initialized = true;
        }
    }
    private static function loadConfig()
    {
        if (!static::$configLoaded) {
            static::$dbTables = parseConfig(TEMPLATE_DATA_DIR, 'db_tables');
            static::$langCodes = parseConfig(TEMPLATE_DATA_DIR, 'languages');
            static::$langISOCodes = array_unique(array_values(static::$langCodes));
            static::$periods = parseConfig(TEMPLATE_DATA_DIR, 'periods');
            static::$security = parseConfig(TEMPLATE_DATA_DIR, 'db_access');
            static::$configLoaded = true;
        }
    }
    public static function dbTables()
    {
        static::loadConfig();

        return static::$dbTables;
    }
    public static function langCodes()
    {
        static::loadConfig();

        return static::$langCodes;
    }
    public static function langISOCodes()
    {
        static::loadConfig();

        return static::$langISOCodes;
    }
    public static function parameters()
    {
        static::init();

        return static::$parameters;
    }
    public static function periods()
    {
        static::loadConfig();

        return static::$periods;
    }
    public static function routing()
    {
        static::init();

        return static::$routing;
    }
    public static function security()
    {
        static::loadConfig();

        return static::$security;
    }
    public static function translations($view = 'layout')
    {
        return parseConfig(TEMPLATE_MESSAGES_DIR, $view);
    }
}
