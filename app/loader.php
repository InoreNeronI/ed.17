<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
}

define('LOADER_DIR', __DIR__);
define('TURBO', false);

// Require and return loader
$loader = realpath(LOADER_DIR.'/../'.sprintf('vendor%s/autoload.php', TURBO ? '-tiny' : ''));
if ($loader !== false) {
    try {
        /** @var \Composer\Autoload\ClassLoader $autoload */
        $autoload = require $loader;
        require LOADER_DIR.'/config/include/constants.php';
        /* @return \Composer\Autoload\ClassLoader */
        return $autoload;
    } catch (Exception $e) {
        /* @throw \Exception */
        throw new \Exception(sprintf('Internal error: %s', $e->getMessage()));
    }
} else {
    /* @throw \Exception */
    throw new \Exception('Vendor files not found, please run "Composer" dependency manager: https://getcomposer.org/');
}
