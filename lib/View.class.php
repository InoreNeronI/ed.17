<?php

namespace App;

use Twig_Environment;
use Twig_Loader_Filesystem;

/**
 * Class Template
 * @package App
 */
class View
{
    /**
     * Construct won't be called inside this class and is uncallable from the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     *
     * @url http://stackoverflow.com/a/11576945
     */
    private function __construct() {}

    /** @var array */
    private static $templatesDirs = array( __DIR__.'/../app/view' );

    /** @var \Twig_Loader_Filesystem */
    private static $loader;

	/** @var \Twig_Environment */
    private static $environment;

	/** @var boolean */
    private static $initialized = false;

	/** @var boolean */
	private static $debug = true;

    /**
     * Twig_Loader_Filesystem loads templates from the file system.
     * This loader can find templates in folders on the file system and is the preferred way to load them.
     *
     * @param string $loader_dir
     * @param string $cache_dir
     */
    private static function initialize($loader_dir = null, $cache_dir = null)
    {
	    if (true === static::$initialized) {
		    throw new \RuntimeException('Do not initialize template-engine twice');
	    }

        if (!is_null($loader_dir))
	        array_push(static::$templatesDirs, $loader_dir);

        static::$loader = new Twig_Loader_Filesystem(static::$templatesDirs);
        static::$environment = new Twig_Environment(static::$loader, array(
            'cache' => is_null($cache_dir) ? static::$templatesDirs[0].'/cache' : $cache_dir,
            'debug' => static::$debug
        ));
        static::$initialized = true;
    }

	/**
	 * Renders a template.
	 *
	 * @param string $slug
	 * @param array $parameters
	 * @param string $extension
	 *
	 * @return string
	 */
    public static function render($slug = 'index', $parameters = array(), $extension = 'html.twig')
    {
        static::initialize();
        return static::$environment->render($slug.'.'.$extension, $parameters);
    }
}
