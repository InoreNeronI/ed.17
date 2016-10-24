<?php

use App\Route;
use Symfony\Component\HttpFoundation;
use Symfony\Component\Routing;

$loader = require __DIR__.'/../app/load.php';

/** @var HttpFoundation\Request $request */
$request = HttpFoundation\Request::createFromGlobals();

/** @var App\Route $routing */
$routing = new Route;

/** @var Routing\RouteCollection $routes */
$routes = $routing::getRoutes();
//print_r($routes);

/** @var Routing\RequestContext $context */
$context = $routing::getContext();

/** @var Routing\Matcher\UrlMatcher $matcher */
$matcher = $routing::getMatcher($context);

// feed the RequestContext
//$context->fromRequest($request);

/** @var string $path */
$path = $request->getPathInfo();    // the URI being requested (e.g. /about) minus any query parameters

// retrieve $_GET and $_POST variables respectively
/*$request->query->get('id');
$request->request->get('category', 'default category');

// retrieve $_SERVER variables
$request->server->get('HTTP_HOST');

// retrieves an instance of UploadedFile identified by "attachment"
$request->files->get('attachment');

// retrieve a $_COOKIE value
$request->cookies->get('PHPSESSID');

// retrieve an HTTP request header, with normalized, lowercase keys
$request->headers->get('host');
$request->headers->get('content_type');

$request->getMethod();    // e.g. GET, POST, PUT, DELETE or HEAD
$request->getLanguages();*/ // an array of languages the client accepts

try {
	$request->attributes->add($matcher->match($path));
	/** @var HttpFoundation\Response $response */
	$response = call_user_func($request->attributes->get('_controller'), $request);

} catch (Routing\Exception\ResourceNotFoundException $e) {
	/** @var HttpFoundation\Response $response */
	$response = new HttpFoundation\Response(sprintf('Not Found: %s', $e->getMessage()), 404);

} catch (Exception $e) {
	/** @var HttpFoundation\Response $response */
	$response = new HttpFoundation\Response(sprintf('An error occurred: %s', $e->getMessage()), 500);
}

// End
$response->send();