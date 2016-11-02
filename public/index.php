<?php

use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpKernel;

$loader = require __DIR__.'/../app/loader.php';

// Creates a Request object based on the current PHP global variables
/** @var HttpFoundation\Request $request */
$request = HttpFoundation\Request::createFromGlobals();

/** @var App\Routing\RouteMap $routing */
$routing = new App\Routing\RouteMap();

/** @var Symfony\Component\Routing\Matcher\UrlMatcher $matcher */
$matcher = $routing->getMatcher();

/** @var HttpKernel\Controller\ControllerResolver $resolver */
$resolver = new HttpKernel\Controller\ControllerResolver();

/** @var EventDispatcher\EventDispatcher $dispatcher */
$dispatcher = new EventDispatcher\EventDispatcher();
$dispatcher->addSubscriber(new HttpKernel\EventListener\RouterListener($matcher, new HttpFoundation\RequestStack()));

/** @var App\Kernel $framework */
$framework = new App\Kernel($matcher, $resolver, $dispatcher);

/** @var HttpFoundation\Response $response */
$response = $framework->handle($request);

$response->send();
