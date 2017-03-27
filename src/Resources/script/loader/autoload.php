<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
includeIfExists(ROOT_DIR.'/src/Resources/config/errorHandler.php');

define('DEBUG', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' &&   // @see http://stackoverflow.com/a/5879078
                php_sapi_name() !== 'cli-server' &&
                !isset($_SERVER['HTTP_CLIENT_IP']) &&
                !isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
                in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', 'fe80::1', '::1', '10.212.11.240', '51.15.133.83']) ? true : false);

function includeIfExists($file)
{
    if (file_exists($file)) {
        return include $file;
    }
}
// Require and return loader
if (!$loader = includeIfExists(ROOT_DIR.'/vendor/autoload.php')) {
    /* @throw \Exception */
    throw new \Exception(
        'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL);
}
/* @return \Composer\Autoload\ClassLoader */
return $loader;
