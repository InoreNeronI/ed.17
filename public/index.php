<?php

use App\Handler\Kernel;
use Symfony\Component\HttpFoundation;

$loader = require dirname(__DIR__).'/app/loader.php';

// Creates a Request object based on the current PHP global variables
/** @var HttpFoundation\Request $request */
$request = HttpFoundation\Request::createFromGlobals();

/** @var Kernel\MicroKernel|Kernel\NanoKernel $framework */
$framework = TURBO ? new Kernel\NanoKernel() : new Kernel\MicroKernel();

/** @var HttpFoundation\Response $response */
$response = $framework->handle($request);

$response->send();
