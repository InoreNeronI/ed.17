<?php

/**
 * @author Martin Mozos <martinmozos@gmail.com>
 * Class def.
 */
final class def extends defDB
{
    private static $configLoaded = false;
    private static $dbCodes;
    private static $dbSecurity;
    private static $dbTargets;
    private static $langCodes;
    private static $langISOCodes;
    private static $metric;
    private static $metricLoaded = false;
    private static $paths;
    private static $pathsLoaded = false;
    private static $periods;
    private static $routesLoaded = false;
    private static $routes;
    private static $sizes;
    private static $styling;

    private static function loadRoutes()
    {
        if (!static::$routesLoaded) {
            static::$routes = parseConfig(CONFIG_DIR, 'routes');
            static::$routesLoaded = true;
        }
    }

    private static function loadPaths()
    {
        if (!static::$pathsLoaded) {
            static::$paths = parseConfig(CONFIG_DIR, 'paths')['paths'];
            static::$pathsLoaded = true;
        }
    }

    private static function loadMetric()
    {
        if (!static::$metricLoaded) {
            static::$metric = parseConfig(CONFIG_DIR, 'metric')['metric'];
            static::$metricLoaded = true;
        }
    }

    private static function loadConfig()
    {
        if (!static::$configLoaded) {
            $config = parseConfig(CONFIG_DIR, 'config');
            static::$dbCodes = $config['codes'];
            static::$dbSecurity = $config['security'];
            static::$dbTargets = $config['targets'];
            static::$langCodes = $config['languages'];
            static::$langISOCodes = array_unique(array_values($config['languages']));
            static::$periods = $config['periods'];
            static::$configLoaded = true;
        }
    }

    public static function dbCodes()
    {
        static::loadConfig();

        return static::$dbCodes;
    }

    public static function dbSecurity()
    {
        static::loadConfig();

        return static::$dbSecurity;
    }

    public static function dbTargets()
    {
        static::loadConfig();

        return static::$dbTargets;
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

    public static function metric()
    {
        static::loadMetric();

        return static::$metric;
    }

    public static function paths()
    {
        static::loadPaths();

        return static::$paths;
    }

    public static function periods()
    {
        static::loadConfig();

        return static::$periods;
    }

    public static function routes()
    {
        static::loadRoutes();

        return static::$routes;
    }
}

/**
 * Class defDb.
 */
class defDb
{
    protected static $dbCredentials;
    protected static $initialized = false;

    private static function loadDbConfig()
    {
        if (!static::$initialized) {
            $connection = parseConfig(CONFIG_DIR, 'connection');
            static::$dbCredentials = $connection/*['credentials']*/;
            static::$initialized = true;
        }
    }

    public static function dbCredentials()
    {
        static::loadDbConfig();

        return static::$dbCredentials;
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

    return isset($config['parameters']) ? $config['parameters'] : $config;
}
