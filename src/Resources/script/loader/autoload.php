<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
include getenv('ROOT_DIR').'/src/Resources/script/errorHandler.php';

if (!getenv('DEBUG')) {
    $debug = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' &&  // @see http://stackoverflow.com/a/5879078
        php_sapi_name() !== 'cli-server' &&
        !isset($_SERVER['HTTP_CLIENT_IP']) &&
        !isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
        in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', 'fe80::1', '::1', '10.212.11.240', '172.18.0.1'/*, '51.15.133.83'*/]) ? true : false;
    putenv('DEBUG='.$debug);
}

// Require and return loader
if (!$loader = include(getenv('ROOT_DIR').'/vendor/autoload.php')) {
    /* @throw \Exception */
    throw new \Exception(
        'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL);
}
/* @return \Composer\Autoload\ClassLoader */
return $loader;
