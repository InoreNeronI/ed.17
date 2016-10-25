<?php

use App\Kernel;
use App\Routing;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpKernel;

$loader = require __DIR__.'/../app/load.php';

/** @var HttpFoundation\Request $request */
$request = HttpFoundation\Request::createFromGlobals();

/** @var App\Routing $routing */
$routing = new Routing;

/** @var Symfony\Component\Routing\RouteCollection $routes */
$routes = $routing::getRoutes();
//print_r($routes);

/** @var Symfony\Component\Routing\RequestContext $context */
$context = $routing::getContext();

/** @var Symfony\Component\Routing\Matcher\UrlMatcher $matcher */
$matcher = $routing::getMatcher($context);

// feed the RequestContext
//$context->fromRequest($request);

/** @var HttpKernel\Controller\ControllerResolver $resolver */
$resolver = new HttpKernel\Controller\ControllerResolver;

/** @var EventDispatcher\EventDispatcher $dispatcher */
$dispatcher = new EventDispatcher\EventDispatcher;
$dispatcher->addSubscriber(new HttpKernel\EventListener\RouterListener($matcher, new HttpFoundation\RequestStack));

/** @var App\Kernel $framework */
$framework = new Kernel($matcher, $resolver, $dispatcher);

/** @var HttpFoundation\Response $response */
$response = $framework->handle($request);
$response->send();