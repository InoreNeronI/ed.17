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
        $this->mapRouteRenders();
    }

    /**
     * Parse routes.
     *
     * @param array $config
     * @param array $messages
     */
    private function mapRouteRenders(array $config = [], array $messages = [])
    {
        $config = empty($config) ? \def::routing() : $config;
        $homeSlug = $config['homeSlug'];
        // Common messages
        $messages = empty($messages) ? \def::translations() : array_merge(\def::translations(), $messages);

        foreach ($config['routes'] as $route) {
            $arguments = [];
            $methods = [];
            $schemes = ['http', 'https'];

            // Feed variables
            if (is_array($route)) {
                if (isset($route['arguments'])) {
                    $arguments = array_unique(array_merge($route['arguments'], $arguments));
                }
                if (isset($route['methods'])) {
                    $methods = array_unique(array_merge($route['methods'], $methods));
                }
                if (isset($route['schemes'])) {
                    $schemes = array_unique(array_merge($route['schemes'], $schemes));
                }
                $route = $route['path'];
            }
            if ($route === $config['homePath']) {
                $routeName = $homeSlug;
            } else {
                // Template name without extension
                $routeName = str_replace('/', '', $route);
            }

            // Merge arguments with common messages
            $arguments = array_merge($messages, $arguments);

            // Merge arguments with current route messages
            $translations = \def::translations("page/$routeName");
            empty($translations) ?: $arguments = array_merge($translations, $arguments);

            // Add route to collection
            static::addRouteRender($route, $routeName, $arguments, $methods, $schemes);
        }
    }
}
