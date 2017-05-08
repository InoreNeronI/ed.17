<?php

/**
 * @author Martin Mozos <martinmozos@gmail.com>
 *
 * Class def.
 */
final class def extends defDB
{
    private static $configLoaded = false;
    private static $dbCodes;
    private static $dbCodesLoaded;
    private static $dbCredentials;
    private static $dbCredentialsLoaded;
    private static $dbTargets;
    private static $dbTargetsLoaded;
    private static $dbUploadersLoaded = false;
    private static $dbUploaders;
    private static $homePath;
    private static $homeSlug;
    private static $langCodes;
    private static $langISOCodes;
    private static $metric;
    private static $metricLoaded = false;
    private static $paths;
    private static $pathsLoaded = false;
    private static $stages;
    private static $routesLoaded = false;
    private static $routes;

    private static function loadCodes()
    {
        if (!static::$dbCodesLoaded) {
            static::$dbCodes = parseConfig(getenv('ROOT_DIR').'/app/config', 'codes')['codes'];
            static::$dbCodesLoaded = true;
        }
    }

    private static function loadCredentials()
    {
        if (!static::$dbCredentialsLoaded) {
            static::$dbCredentials = parseConfig(getenv('ROOT_DIR').'/app/config', 'credentials')['credentials'];
            static::$dbCredentialsLoaded = true;
        }
    }

    private static function loadTargets()
    {
        if (!static::$dbTargetsLoaded) {
            static::$dbTargets = parseConfig(getenv('ROOT_DIR').'/app/config', 'targets')['targets'];
            static::$dbTargetsLoaded = true;
        }
    }

    private static function loadUploaders()
    {
        if (!static::$dbUploadersLoaded) {
            static::$dbUploaders = parseConfig(getenv('ROOT_DIR').'/app/config', 'uploaders')['uploaders'];
            static::$dbUploadersLoaded = true;
        }
    }

    private static function loadRoutes()
    {
        if (!static::$routesLoaded) {
            static::$routes = parseConfig(getenv('CONFIG_DIR'), 'routes')['routes'];
            static::$routesLoaded = true;
        }
    }

    private static function loadPaths()
    {
        if (!static::$pathsLoaded) {
            static::$paths = parseConfig(getenv('CONFIG_DIR'), 'paths')['paths'];
            static::$pathsLoaded = true;
        }
    }

    private static function loadMetric()
    {
        if (!static::$metricLoaded) {
            static::$metric = parseConfig(getenv('CONFIG_DIR'), 'metric')['metric'];
            static::$metricLoaded = true;
        }
    }

    private static function loadConfig()
    {
        if (!static::$configLoaded) {
            $config = parseConfig(getenv('ROOT_DIR').'/app/config', 'config')['configuration'];
            static::$homePath = $config['homepage_path'];
            static::$homeSlug = $config['homepage_slug'];
            static::$langCodes = $config['languages'];
            static::$langISOCodes = array_unique(array_values($config['languages']));
            static::$stages = $config['stages'];
            static::$configLoaded = true;
        }
    }

    public static function dbCodes()
    {
        static::loadCodes();

        return static::$dbCodes;
    }

    public static function dbCredentials()
    {
        static::loadCredentials();

        return static::$dbCredentials;
    }

    public static function dbTargets()
    {
        static::loadTargets();

        return static::$dbTargets;
    }

    public static function dbUploaders()
    {
        static::loadUploaders();

        return static::$dbUploaders;
    }

    public static function homePath()
    {
        static::loadConfig();

        return static::$homePath;
    }

    public static function homeSlug()
    {
        static::loadConfig();

        return static::$homeSlug;
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

    public static function stages()
    {
        static::loadConfig();

        return static::$stages;
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
    private static $adminUsername;
    private static $adminPassword;
    private static $dbDist;
    private static $dbLocal;
    private static $userEntity;
    private static $extraEntity;
    private static $initialized = false;

    private static function loadDbConfig()
    {
        if (!static::$initialized) {
            $connectionConfig = parseConfig(getenv('ROOT_DIR'), 'app/connection');
            $connectionDist = $connectionConfig['default_connection'];
            $connections = $connectionConfig['connections'];
            $localUsers = $connectionConfig['users']['local'];
            static::$adminUsername = $localUsers['admin']['name'];
            static::$adminPassword = $localUsers['admin']['pw'];
            static::$dbDist = $connections[$connectionDist];
            static::$dbLocal = $connections['local'];
            static::$userEntity = $connectionConfig['users'][$connectionDist];
            static::$extraEntity = $connectionConfig['users']['extra'];
            static::$initialized = true;
        }
    }

    public static function adminUsername()
    {
        static::loadDbConfig();

        return static::$adminUsername;
    }

    public static function adminPassword()
    {
        static::loadDbConfig();

        return static::$adminPassword;
    }

    public static function dbDist()
    {
        static::loadDbConfig();

        return static::$dbDist;
    }

    public static function dbLocal()
    {
        static::loadDbConfig();

        return static::$dbLocal;
    }

    public static function userEntity()
    {
        static::loadDbConfig();

        return static::$userEntity;
    }

    public static function extraEntity()
    {
        static::loadDbConfig();

        return static::$extraEntity;
    }
}

/**
 * Function parseConfig.
 *
 * @param string      $path
 * @param string|null $filename
 *
 * @return array|mixed
 */
function parseConfig($path, $filename = null)
{
    is_null($filename) ?: $path = "$path/$filename.yml";
    if (is_file($path)) {
        /** @var array $config */
        $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($path));

        return isset($config['parameters']) ? $config['parameters'] : $config;
    }
}
