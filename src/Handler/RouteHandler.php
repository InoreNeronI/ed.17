<?php

namespace App\Handler;

use Symfony\Component\Routing;

/**
 * Class RouteHandler.
 */
class RouteHandler
{
    /** @var Routing\RouteCollection $routes */
    protected $routes;

    /** @var bool $loaded */
    protected $loaded = false;

    /**
     * RouteHandler constructor.
     *
     * @param Routing\RouteCollection $routes
     */
    public function __construct(Routing\RouteCollection $routes)
    {
        if (true === $this->loaded) {
            throw new \RuntimeException('Do not add the "routing loader" twice');
        }

        $this->setRouteCollection($routes);
        $this->mapRoutes();
        $this->loaded = true;
    }

    /**
     * Sets route collection.
     *
     * @param Routing\RouteCollection $routes
     */
    protected function setRouteCollection(Routing\RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * @return Routing\RouteCollection A RouteCollection instance
     */
    public function getRouteCollection()
    {
        return $this->routes;
    }

    /**
     * Retrieves requests' context.
     *
     * @return Routing\RequestContext
     */
    public function getContext()
    {
        return new Routing\RequestContext();
    }

    /**
     * Retrieves url matcher.
     *
     * @param Routing\RequestContext|null $context
     *
     * @return Routing\Matcher\UrlMatcher
     */
    public function getMatcher(Routing\RequestContext $context = null)
    {
        return new Routing\Matcher\UrlMatcher($this->routes, is_null($context) ? $this->getContext() : $context);
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
     * @param array  $translations
     * @param array  $defaults
     * @param array  $methods
     * @param array  $requirements ['parameter' => '\d+', 'month' => '[0-9]{4}-[0-9]{2}', 'subdomain' => 'www|m']
     * @param array  $schemes
     */
    public function addRouteRender($path = '/', $routeName = 'index', $translations = [], $defaults = [], $methods = ['GET', 'POST'], $requirements = [], $schemes = ['http', 'https'])
    {
        $defaultController = ['_controller' => 'App\\Controller\\ContentRenderController::renderAction'];
        $defaults = empty($defaults) ? $defaultController : isset($defaults['_controller']) ? $defaults : array_merge($defaults, $defaultController);
        if (!empty($translations)) {
            $defaults['messages'] = empty($defaults['messages']) ? $translations : array_merge($defaults['messages'], $translations);
        }
        $options = [];
        $host = ''; // '{subdomain}.example.com';
        $condition = '';
        $route = new Routing\Route($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
        $this->routes->add($routeName, $route);
    }

    /**
     * Parse routes.
     */
    private function mapRoutes()
    {
        /** @var array $route */
        foreach (\def::routing() as $route) {
            // Init variables
            $defaults = [];
            $methods = [];
            $requirements = [];
            $schemes = [];

            // Feed variables
            if (is_array($route)) {
                // Feed variables
                if (isset($route['defaults'])) {
                    $defaults = array_unique($route['defaults']);
                }
                if (isset($route['controller'])) {
                    $defaults['_controller'] = $route['controller'];
                }
                if (isset($route['methods'])) {
                    $methods = array_unique($route['methods']);
                }
                if (isset($route['requirements'])) {
                    $requirements = array_unique($route['requirements']);
                }
                if (isset($route['schemes'])) {
                    $schemes = array_unique($route['schemes']);
                }
                $routePath = $route['path'];
            } else {
                $routePath = $route;
            }
            if (isset($route['name'])) {
                $routeName = $route['name'];
            } elseif ($routePath === \def::paths()['home_url_path']) {
                $routeName = \def::paths()['home_url_slug'];
            } else {
                // Template name without extension
                $routeName = str_replace('/', '', $routePath);
            }

            // Current route messages
            $translations = \def::translations("page/$routeName");

            // Merge defaults with common messages
            $defaults['messages'] = empty($defaults['messages']) ? \def::translations() : array_unique(array_merge(\def::translations(), $defaults['messages']));

            // Add route to collection
            $this->addRouteRender($routePath, $routeName, $translations, $defaults, $methods, $requirements, $schemes);
        }
    }
}
