<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
}

// Set root dir
putenv('ROOT_DIR='.dirname(dirname(dirname(dirname(__DIR__)))));

// Require error handler
require getenv('ROOT_DIR').'/src/Resources/script/errorHandler.php';

// Require loader
/* @var Composer\Autoload\ClassLoader */
if (!$loader = require(getenv('ROOT_DIR').'/vendor/autoload.php')) {
    /* @throw \Exception */
    throw new \Exception(
        'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL);
}

// Set debug
if ($debug = //strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' &&  // @see http://stackoverflow.com/a/5879078
    php_sapi_name() !== 'cli-server' &&
    !isset($_SERVER['HTTP_CLIENT_IP']) &&
    !isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
    in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', 'fe80::1', '::1', '10.212.11.240', '172.18.0.1'/*, '51.15.133.83'*/]) ? true : false) {
    Symfony\Component\Debug\Debug::enable();
}
putenv('DEBUG='.$debug);

// Load `.env` files environment-variables
if (is_file(getenv('ROOT_DIR').'/.env')) {
    //(new \Symfony\Component\Dotenv\Dotenv())->load(getenv('ROOT_DIR').'/.env');
    (new \Dotenv\Dotenv(getenv('ROOT_DIR').'/.env'))->load();
}

// Require classes and functions
require getenv('ROOT_DIR').'/src/Resources/script/loader/functions.php';

// Set config files' path
putenv('CONFIG_DIR='.getenv('ROOT_DIR').'/src/Resources/config');

// Return loader
/* @return \Composer\Autoload\ClassLoader */
return $loader;
