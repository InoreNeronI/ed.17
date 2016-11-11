<?php

namespace App\Routing;

use Symfony\Component\Routing as BaseRouting;

/**
 * Class Route.
 */
class Route
{
    /** @var BaseRouting\RouteCollection $routes */
    protected $routes;

    /** @var bool $loaded */
    protected $loaded = false;

    /**
     * Routing constructor.
     */
    public function __construct()
    {
        if (true === $this->loaded) {
            throw new \RuntimeException('Do not add the "routing loader" twice');
        }

        $this->setRouteCollection(new BaseRouting\RouteCollection());
        $this->loaded = true;
    }

    /**
     * Sets route collection.
     *
     * @param BaseRouting\RouteCollection $routes
     */
    protected function setRouteCollection(BaseRouting\RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * @return BaseRouting\RouteCollection A RouteCollection instance
     */
    public function getRouteCollection()
    {
        return $this->routes;
    }

    /**
     * Retrieves requests' context.
     *
     * @return BaseRouting\RequestContext
     */
    public function getContext()
    {
        return new BaseRouting\RequestContext();
    }

    /**
     * Retrieves url matcher.
     *
     * @param BaseRouting\RequestContext|null $context
     *
     * @return BaseRouting\Matcher\UrlMatcher
     */
    public function getMatcher(BaseRouting\RequestContext $context = null)
    {
        return new BaseRouting\Matcher\UrlMatcher($this->routes, is_null($context) ? $this->getContext() : $context);
    }

    // We register a route by invoking the add method with specific arguments. The first argument is a unique name for this route.
    // Next we instantiate a Route object with our route. Our route is /docs/{ID} Our associative array contains the controller that we
    // want to resolve with this route, DocumentController and the method on that controller that this particular route invokes viewDocumentAction.
    // separated by the :: token. We can also pass in arguments that contain a default value if needed, and they will be available in our method's parameters.
    /**
     * Adds a route to collection.
     *
     * @param string $path
     * @param string $routeName
     * @param array  $messages
     * @param array  $defaults
     * @param array  $methods
     * @param array  $requirements ['parameter' => '\d+', 'month' => '[0-9]{4}-[0-9]{2}', 'subdomain' => 'www|m']
     * @param array  $schemes
     */
    public function addRouteRender($path = '/', $routeName = 'index', $messages = [], $defaults = [], $methods = ['GET', 'POST'], $requirements = [], $schemes = ['http', 'https'])
    {
        $defaultController = ['_controller' => 'App\\Routing\\Controller\\ContentRenderController::renderAction'];
        $defaults = empty($defaults) ? $defaultController : isset($defaults['_controller']) ? $defaults : array_merge($defaults, $defaultController);
        empty($messages) ?: $defaults['messages'] = empty($defaults['messages']) ? $messages : array_merge($defaults['messages'], $messages);
        $options = [];
        $host = ''; // '{subdomain}.example.com';
        $condition = '';
        $route = new BaseRouting\Route($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
        $this->routes->add($routeName, $route);
    }
}
