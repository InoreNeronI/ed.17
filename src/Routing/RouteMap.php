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
            // Common messages
            $messages = \def::translations();

            // Feed variables
            if (is_array($route)) {
                // Feed variables
                if (isset($route['defaults'])) {
                    $defaults = array_unique($route['defaults']);
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
            if ($routePath === \def::parameters()['home_path']) {
                $routeName = \def::parameters()['home_slug'];
            } else {
                // Template name without extension
                $routeName = str_replace('/', '', $routePath);
            }

            // Merge defaults with common messages
            $defaults = array_merge($messages, $defaults);

            // Merge defaults with current route messages
            $translations = \def::translations("page/$routeName");
            empty($translations) ?: $messages = array_merge($messages, $translations);

            // Add route to collection
            $this->addRouteRender($routePath, $routeName, $messages, $defaults, $methods, $requirements, $schemes);
        }
    }
}
