<?php

use App\Kernel;
use Symfony\Component\HttpFoundation;

$loader = require dirname(__DIR__).'/app/loader.php';

// Creates a Request object based on the current PHP global variables
/** @var HttpFoundation\Request $request */
$request = HttpFoundation\Request::createFromGlobals();

/** @var Kernel\Fast|Kernel\Micro $framework */
$framework = new Kernel\Micro();

/** @var HttpFoundation\Response $response */
$response = $framework->handle($request);

$response->send();