<?php
namespace App;

use Symfony\Component\Routing;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Route
 * @package App
 */
class Route
{
	/** @var array $translations */
	private static $translations;

	/** @var array $paths */
	private static $paths;

	/** @var Routing\RouteCollection $routes */
	private static $routes;

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

		static::$translations = Yaml::parse(file_get_contents(__DIR__.'/../../app/config/messages.yml'));
		static::$paths = Yaml::parse(file_get_contents(__DIR__.'/../../app/config/routes.yml'));
		static::$routes = new Routing\RouteCollection();
		static::$initialized = true;

		foreach (static::$paths['paths'] as $path) {
			if (is_array($path)) {
				if (isset($path['methods']))
					$methods = array_merge($path['methods'], $methods);
				if (isset($path['schemes']))
					$schemes = array_merge($path['schemes'], $schemes);
				if (isset($path['parameters']))
					$parameters = array_merge($path['parameters'], $parameters);
				$path = $path['path'];
			}
			if ($path === '/') {
				$slug = static::$paths['indexView'];   // Template name without extension
				if ($slug !== 'index')
					$parameters = array_merge(static::$translations['index'], $parameters);
			} else
				$slug = str_replace('/', '', $path);

			$parameters = isset(static::$translations[$slug]) ? array_merge(static::$translations[$slug], $parameters) : $parameters;
			static::addRoute($path, $slug, $methods, $schemes, $parameters);
		}
	}


	// We register a route by invoking the add method with specific arguments. The first argument is a unique name for this route.
	// Next we instantiate a Route object with our route. Our route is /docs/{ID} Our associative array contains the controller that we
	// want to resolve with this route, DocumentController and the method on that controller that this particular route invokes viewDocumentAction.
	// separated by the :: token. We can also pass in arguments that contain a default value if needed, and they will be available in our method's parameters.
	/**
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
			new Routing\Route(
				$path, // path
				array('_controller' => 'App\\Controller\\Test::'.$slug.'Action', 'parameters' => $parameters), // defaults
				array(), // array('month' => '[0-9]{4}-[0-9]{2}', 'subdomain' => 'www|m'), // requirements
				array(), // options
				'', //'{subdomain}.example.com', // host
				$schemes, // schemes
				$methods, // methods
				'' // condition
			)
		);
	}

	/**
	 * @param string $slug
	 */
	public static function removeRoute($slug)
	{
		static::$routes->remove($slug);
	}

	/**
	 * @param string $slug
	 *
	 * @return null|Routing\Route
	 */
	public static function getRoute($slug)
	{
		return static::$routes->get($slug);
	}

	/**
	 * @return Routing\RouteCollection
	 */
	public static function getRoutes()
	{
		return static::$routes;
	}

	/**
	 * @return Routing\RequestContext
	 */
	public static function getContext()
	{
		return new Routing\RequestContext(static::$paths['indexView']);
	}

	/**
	 * @param Routing\RequestContext|null $context
	 *
	 * @return Routing\Matcher\UrlMatcher
	 */
	public static function getMatcher($context = null)
	{
		return new Routing\Matcher\UrlMatcher(static::$routes, is_null($context) ? static::getContext() : $context);
	}

}
