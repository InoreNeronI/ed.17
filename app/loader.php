<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
// Require and return loader
$loader = realpath(LOADER_DIR.sprintf('/vendor%s/autoload.php', TURBO ? '-tiny' : ''));

if ($loader !== false) {
    try {
        /** @var \Composer\Autoload\ClassLoader $autoload */
        $autoload = require $loader;
        require LOADER_DIR.'/app/config/include/constants.php';
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
