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
     * @param array $base_arguments
     * @param array $base_methods
     * @param array $base_schemes
     */
    private function mapRouteRenders(array $config = [], array $messages = [], array $base_arguments = [], array $base_methods = ['GET', 'POST'], array $base_schemes = ['http', 'https'])
    {
        $config = empty($config) ? \def::routing() : $config;
        // Common messages
        $messages = empty($messages) ? \def::translations() : array_merge(\def::translations(), $messages);
        $homeSlug = $config['homeSlug'];

        foreach ($config['routes'] as $route) {
            $arguments = $base_arguments;
            $methods = $base_methods;
            $schemes = $base_schemes;

            // Feed variables
            if (is_array($route)) {
                if (isset($route['arguments'])) {
                    $arguments = array_merge($route['arguments'], $arguments);
                }
                if (isset($route['methods'])) {
                    $methods = array_merge($route['methods'], $methods);
                }
                if (isset($route['schemes'])) {
                    $schemes = array_merge($route['schemes'], $schemes);
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
