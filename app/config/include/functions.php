<?php

/**
 * Class def.
 */
final class def extends defDB
{
    private static $configLoaded = false;
    private static $langCodes;
    private static $langISOCodes;
    private static $paths;
    private static $periods;
    private static $routing;
    private static $styling;

    private static function loadConfig()
    {
        if (!static::$configLoaded) {
            $config = parseConfig(CONFIG_DIR, 'config');
            static::$langCodes = $config['languages'];
            static::$langISOCodes = array_unique(array_values($config['languages']));
            static::$paths = $config['paths'];
            static::$periods = $config['periods'];
            static::$routing = $config['routes'];
            static::$styling = $config['styles'];
            static::$configLoaded = true;
        }
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
    public static function paths()
    {
        static::loadConfig();

        return static::$paths;
    }
    public static function periods()
    {
        static::loadConfig();

        return static::$periods;
    }
    public static function routing()
    {
        static::loadConfig();

        return static::$routing;
    }
    public static function styling()
    {
        static::loadConfig();

        return static::$styling;
    }
    public static function translations($view = 'layout')
    {
        return parseConfig(TRANSLATIONS_DIR, $view);
    }
}

/**
 * Class defDb.
 */
class defDb
{
    protected static $dbCodes;
    protected static $dbCredentials;
    protected static $dbSecurity;
    protected static $dbTables;
    protected static $initialized = false;

    private static function loadDbConfig()
    {
        if (!static::$initialized) {
            $database = parseConfig(CONFIG_DIR, 'database');
            static::$dbCodes = $database['codes'];
            static::$dbCredentials = $database['credentials'];
            static::$dbSecurity = $database['security'];
            static::$dbTables = $database['tables'];
            static::$initialized = true;
        }
    }
    public static function dbCodes()
    {
        static::loadDbConfig();

        return static::$dbCodes;
    }
    public static function dbCredentials()
    {
        static::loadDbConfig();

        return static::$dbCredentials;
    }
    public static function dbSecurity()
    {
        static::loadDbConfig();

        return static::$dbSecurity;
    }
    public static function dbTables()
    {
        static::loadDbConfig();

        return static::$dbTables;
    }
}

/**
 * Function parseConfig.
 *
 * @param string      $path
 * @param string|null $filename
 *
 * @return array
 */
function parseConfig($path, $filename = null)
{
    is_null($filename) ?: $path = "$path/$filename.yml";
    if (is_file($path)) {
        /** @var array $config */
        $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($path));
    } else {
        return [];
    }

    return $filename === 'parameters' ? $config['parameters'] : $config;
}
