<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
}

// Set root dir
putenv('ROOT_DIR='.(php_sapi_name() === 'cli' ? getcwd() : dirname(getcwd())));

// Require error handler
require getenv('ROOT_DIR').'/vendor/va/Resources/script/errorHandler.php';

// Require loader
/* @var Composer\Autoload\ClassLoader */
if (!class_exists('ClassLoader') && !$loader = require(getenv('ROOT_DIR').'/vendor/autoload.php')) {
    /* @throw \Exception */
    throw new \Exception(
        'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL);
}

// Load `.env` files environment-variables
if (is_file(getenv('ROOT_DIR').'/.env')) {
    //(new \Symfony\Component\Dotenv\Dotenv())->load(getenv('ROOT_DIR').'/.env');
    (new \Dotenv\Dotenv(getenv('ROOT_DIR')))->load();
}

// Set debug
// @see http://stackoverflow.com/a/5879078
if (getenv('DEBUG') !== false) {
    Symfony\Component\Debug\Debug::enable();
} else {
    putenv('DEBUG=false');
}

// Require classes and functions
require getenv('ROOT_DIR').'/vendor/va/Resources/script/loader/functions.php';

// Set config files' path
putenv('CONFIG_DIR='.getenv('ROOT_DIR').'/vendor/va/Resources/config');
