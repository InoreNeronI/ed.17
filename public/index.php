<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
use App\Handler\Kernel;

if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
}
define('LOADER_DIR', dirname(__DIR__));
define('TURBO', false);

require LOADER_DIR.'/app/loader.php';
require LOADER_DIR.'/app/config/include/constants.php';

/** @var Kernel\MicroKernel|Kernel\NanoKernel $kernel */
$app = TURBO ? new Kernel\NanoKernel() : new Kernel\MicroKernel(DEBUG);
$app->loadClassCache();

// Handles and sends a Request object based on the current PHP global variables
$app->handle(Symfony\Component\HttpFoundation\Request::createFromGlobals())->send();