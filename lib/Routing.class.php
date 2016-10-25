<?php
namespace App;

use Symfony\Component\Routing as BaseRouting;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Route
 * @package App
 */
class Routing
{
	/** @var string $homeSlug */
	private static $homeSlug;

	/** @var BaseRouting\RouteCollection $routes */
	private static $routes;

	/** @var array $messages */
	private static $messages;

	/** @var boolean */
	private static $initialized = false;

	/**
	 * Route constructor.
	 *
	 * @param array $methods
	 * @param array $schemes
	 * @param array $parameters
	 */
	public function __construct($methods = array('GET'), $schemes = array('http', 'https'), $parameters = array())
	{
		if (true === static::$initialized)
			return;

		$routes = Yaml::parse(file_get_contents(__DIR__.'/../app/config/routing.yml'));
		$homePath = $routes['homePath'];
		static::$homeSlug = $routes['homeSlug'];
		static::$messages = Yaml::parse(file_get_contents(__DIR__.'/../app/config/messages.yml'));
		static::$routes = new BaseRouting\RouteCollection();
		static::$initialized = true;

		foreach ($routes['routes'] as $route) {
			if (is_array($route)) {
				if (isset($route['methods']))
					$methods = array_merge($route['methods'], $methods);
				if (isset($route['schemes']))
					$schemes = array_merge($route['schemes'], $schemes);
				if (isset($route['parameters']))
					$parameters = array_merge($route['parameters'], $parameters);
				$route = $route['path'];
			}
			// Merge common messages
			$parameters = array_merge(static::$messages['common'], $parameters);
			// Merge home specific messages and set slug
			if ($route === $homePath) {
				$slug = static::$homeSlug;
				if ($slug !== 'home')
					$parameters = array_merge(static::$messages['home'], $parameters);
			} else
				$slug = str_replace('/', '', $route);   // Template name without extension
			// Merge current view messages
			$parameters = isset(static::$messages[$slug]) ? array_merge(static::$messages[$slug], $parameters) : $parameters;
			// Add route to collection
			static::addRoute($route, $slug, $methods, $schemes, $parameters);
		}
	}


	// We register a route by invoking the add method with specific arguments. The first argument is a unique name for this route.
	// Next we instantiate a Route object with our route. Our route is /docs/{ID} Our associative array contains the controller that we
	// want to resolve with this route, DocumentController and the method on that controller that this particular route invokes viewDocumentAction.
	// separated by the :: token. We can also pass in arguments that contain a default value if needed, and they will be available in our method's parameters.
	/**
	 * Adds a route to collection.
	 *
	 * @param string $path
	 * @param string $slug
	 * @param array $methods
	 * @param array $schemes
	 * @param array $parameters
	 */
	private static function addRoute($path = '/'/*'/tests/{testId}'*/, $slug = 'index', $methods = array('GET'), $schemes = array('http', 'https'), $parameters = array())
	{
		static::$routes->add(
			$slug,
			new BaseRouting\Route(
				$path, // path
				array('_controller' => 'App\\Controller::action', 'parameters' => $parameters), // defaults
				array(), //array('month' => '[0-9]{4}-[0-9]{2}', 'subdomain' => 'www|m'), // requirements
				array(), // options
				'', //'{subdomain}.example.com', // host
				$schemes, // schemes
				$methods, // methods
				'' // condition
			)
		);
	}

	/**
	 * Removes a route from collection.
	 *
	 * @param string $slug
	 */
	public static function removeRoute($slug)
	{
		static::$routes->remove($slug);
	}

	/**
	 * Retrieves a route from collection.
	 *
	 * @param string $slug
	 *
	 * @return BaseRouting\Route|null
	 */
	public static function getRoute($slug)
	{
		return static::$routes->get($slug);
	}

	/**
	 * Retrieves route collection.
	 *
	 * @return BaseRouting\RouteCollection
	 */
	public static function getRoutes()
	{
		return static::$routes;
	}

	/**
	 * Retrieves requests' context.
	 *
	 * @return BaseRouting\RequestContext
	 */
	public static function getContext()
	{
		return new BaseRouting\RequestContext(static::$homeSlug);
	}

	/**
	 * Retrieves url matcher.
	 *
	 * @param BaseRouting\RequestContext|null $context
	 *
	 * @return BaseRouting\Matcher\UrlMatcher
	 */
	public static function getMatcher($context = null)
	{
		return new BaseRouting\Matcher\UrlMatcher(static::$routes, is_null($context) ? static::getContext() : $context);
	}
}
