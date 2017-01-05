<?php

namespace App\Handler;

use Symfony\Component\Routing;
use Symfony\Component\Yaml;

/**
 * Class RouteHandler.
 */
class RouteHandler
{
    /** @var bool $loaded */
    protected $loaded = false;

    /** @var Routing\RouteCollection $routeCol */
    protected $routeCol;

    /** @var array $routeList */
    protected $routeList;

    /** @var string */
    protected $translationsDir;

    /**
     * RouteHandler constructor.
     *
     * @param array  $parsedRoutes
     * @param string $translationsDir
     * @param string $homeUrlPath
     * @param string $homeUrlSlug
     */
    public function __construct(array $parsedRoutes, $translationsDir, $homeUrlPath, $homeUrlSlug)
    {
        $this->routeCol = new Routing\RouteCollection();
        $this->translationsDir = $translationsDir;
        $this->mapRoutes($parsedRoutes, $homeUrlPath, $homeUrlSlug);
     //   dump($this->routeList);exit;
        $this->loaded = true;
    }

    /**
     * @param string $view
     *
     * @return array
     */
    private function translations($view = 'layout')
    {
        return is_file($file = $this->translationsDir.'/'.$view.'.yml') ? Yaml\Yaml::parse(file_get_contents($file)) : [];
    }

    /**
     * Sets route collection.
     *
     * @param Routing\RouteCollection $routeCol
     *
     * @return $this
     */
    protected function setRouteCollection(Routing\RouteCollection $routeCol)
    {
        $this->routeCol = $routeCol;

        return $this;
    }

    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * @return Routing\RouteCollection A RouteCollection instance
     */
    public function getRouteCollection()
    {
        return $this->routeCol;
    }

    /**
     * Sets route list.
     *
     * @param array $routeList
     *
     * @return $this
     */
    protected function setRouteList(array $routeList)
    {
        $this->routeList = $routeList;

        return $this;
    }

    /**
     * Gets the RouteList associated with this Router.
     *
     * @return array RouteList
     */
    public function getRouteList()
    {
        return $this->routeList;
    }

    // We register a route by invoking the add method with specific arguments. The first argument is a unique name for this route.
    // Next we instantiate a Route object with our route. Our route is /docs/{ID} Our associative array contains the controller that we
    // want to resolve with this route, DocumentController and the method on that controller that this particular route invokes viewDocumentAction.
    // separated by the :: token. We can also pass in arguments that contain a default value if needed, and they will be available in our method's parameters.

    /**
     * Adds a route to collection.
     *
     * @param string $path
     * @param string $name
     * @param array  $defaults
     * @param array  $methods
     * @param array  $requirements
     * @param array  $schemes
     * @param array  $options
     * @param string $host
     * @param string $condition
     */
    public function addRouteRender(
        $path = '/',
        $name = 'index',
        $defaults = [],
        $methods = ['GET', 'POST'],
        $requirements = [], // ['parameter' => '\d+', 'month' => '[0-9]{4}-[0-9]{2}', 'subdomain' => 'www|m'],
        $schemes = ['http', 'https'],
        $options = [],
        $host = '', // '{subdomain}.example.com',
        $condition = '')
    {
        // Current route messages
        $translations = $this->translations("page/$name");

        if (!empty($translations)) {
            $defaults['messages'] = empty($defaults['messages']) ? $translations : array_merge($defaults['messages'], $translations);
        }

        $route = new Routing\Route($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
        if (TURBO) {
            $this->routeCol->add($name, $route);
        } else {
            $this->routeList[$name] = $route;
        }
    }

    /**
     * Parse routes.
     *
     * @param array  $routes
     * @param string $homeUrlPath
     * @param string $homeUrlSlug
     */
    private function mapRoutes($routes = [], $homeUrlPath = '/', $homeUrlSlug = 'home')
    {
        if (true === $this->loaded) {
            throw new \RuntimeException('Do not add the "routing loader" twice');
        }

        /** @var array $route */
        foreach ($routes as $route) {
            // Feed variables
            $defaults = isset($route['defaults']) ? array_unique($route['defaults']) : [];
            $methods = isset($route['methods']) ? array_unique($route['methods']) : [];
            $requirements = isset($route['requirements']) ? array_unique($route['requirements']) : [];
            $schemes = isset($route['schemes']) ? array_unique($route['schemes']) : [];

            // Feed variables
            if (is_array($route)) {
                $routePath = $route['path'];
                if (!isset($route['action'])) {
                    $route['action'] = 'render';
                }
            } else {
                $routePath = $route;
                $route = ['action' => 'render'];
            }
            if (isset($route['name'])) {
                $routeName = $route['name'];
            } elseif ($routePath === $homeUrlPath) {
                $routeName = $homeUrlSlug;
            } else {
                // Template name without extension
                $routeName = str_replace('/', '', $routePath);
            }
            if (isset($route['controller']) && !isset($route['action'])) {
                $defaults['_controller'] = $route['controller'];
            } elseif (TURBO && isset($route['action'])) {
                $defaults['_controller'] = 'App\\Controller\\BaseController::'.$route['action'].'Action';
            } elseif (!TURBO && isset($route['action'])) {
                $defaults['_controller'] = 'AppBundle\\Action\\'.ucfirst($route['action'].'Action');
            }
            // Merge defaults with common messages
            $defaults['messages'] = empty($defaults['messages']) ? $this->translations() : array_unique(array_merge($this->translations(), $defaults['messages']));

            // Add route to collection
            $this->addRouteRender($routePath, $routeName, $defaults, $methods, $requirements, $schemes);
        }
    }
}
