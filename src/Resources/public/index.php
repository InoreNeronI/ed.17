<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
}
define('ROOT_DIR', dirname(__DIR__));

/* @var Composer\Autoload\ClassLoader */
require ROOT_DIR.'/src/Resources/script/loader/autoload.php';

if (DEBUG) {
    Symfony\Component\Debug\Debug::enable();
}

/** @var Kernel\MicroKernel|Kernel\BaseKernel $kernel */
//$app = TURBO ? require ROOT_DIR.'/src/Resources/script/loader/constants.php' : new Kernel\MicroKernel(DEBUG);
$app = require ROOT_DIR.'/src/Resources/script/loader/constants.php';
//$app->loadClassCache();

// Handles and sends a Request object based on the current PHP global variables
$app->handle(Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();
