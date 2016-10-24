<?php

namespace App;

use Twig_Environment;
use Twig_Loader_Filesystem;

/**
 * Class Template
 * @package App
 */
class Template
{
    /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     * @url http://stackoverflow.com/a/11576945
     */
    private function __construct() {}

    /** @var boolean */
    private static $debug = true;

    /** @var string */
    private static $templatesDir = __DIR__.'/../../app/view';

    /** @var \Twig_Loader_Filesystem */
    private static $loader = null;

	/** @var \Twig_Environment */
    private static $environment = null;

	/** @var boolean */
    private static $initialized = false;

    /**
     * @param string $loader_dir    Twig_Loader_Filesystem loads templates from the file system. This loader can find templates in folders on the file system and is the preferred way to load them:
     * @param string $cache_dir
     */
    private static function initialize($loader_dir = null, $cache_dir = null)
    {
	    if (true === static::$initialized) {
		    throw new \RuntimeException('Do not initialize template-engine twice');
	    }

        if (!is_null($loader_dir))
	        static::$templatesDir = $loader_dir;

        static::$loader = new Twig_Loader_Filesystem([ static::$templatesDir ]);
        static::$environment = new Twig_Environment(static::$loader, array(
            'cache' => is_null($cache_dir) ? static::$templatesDir.'/cache' : $cache_dir,
            'debug' => static::$debug
        ));
        static::$initialized = true;
    }

	/**
	 * @param string $template
	 * @param array $parameters
	 * @param string $extension
	 *
	 * @return string
	 */
    public static function render($template = 'index', $parameters = array(), $extension = 'html.twig')
    {
        static::initialize();
        return static::$environment->render($template.'.'.$extension, $parameters);
    }
}
