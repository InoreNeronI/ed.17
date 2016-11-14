<?php

namespace App\Routing;

/**
 * Class RouteRenderParser.
 */
final class RouteMap extends Route
{
    /**
     * RouteMap constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->mapRoutes();
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
