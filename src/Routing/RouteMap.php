<?php

namespace App\Routing;

/**
 * Class RouteRenderParser.
 */
final class RouteMap extends Routing
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
	    $messages = empty($messages) ? \def::messages() : $messages;
	    $homeSlug = $config['homeSlug'];

        foreach ($config['routes'] as $route) {
            $arguments = $base_arguments;
            $methods = $base_methods;
            $schemes = $base_schemes;
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
            // Merge common messages
            $arguments = array_merge($messages['common'], $arguments);
            // Merge homepage specific messages and set routeName
            if ($route === $config['homePath']) {
                $routeName = $homeSlug;
                if ($routeName !== 'home') {
                    $arguments = array_merge($messages['home'], $arguments);
                }
            } else {
                // Template name without extension
                $routeName = str_replace('/', '', $route);
            }
            // Merge current view messages
            $arguments = isset($messages[$routeName]) ? array_merge($messages[$routeName], $arguments) : $arguments;
            // Add route to collection
            static::addRouteRender($route, $routeName, $arguments, $methods, $schemes);
        }
    }
}
