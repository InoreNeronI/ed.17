<?php

namespace App;

use Twig_Environment;
use Twig_Loader_Filesystem;

const TEMPLATE_FILES_DIR  = '/app/view';
const TEMPLATE_EXTENSION  = 'html.twig';

/**
 * Class Template
 * @package App
 */
class View
{
    /** @var array */
    private static $templatesDirs = TEMPLATE_FILES_DIR;

    /** @var string */
    private static $templatesExtension = TEMPLATE_EXTENSION;

    /** @var \Twig_Loader_Filesystem */
    private static $loader;

	/** @var \Twig_Environment */
    private static $environment;

	/** @var boolean */
    private static $initialized = false;

	/** @var boolean */
	private static $debug = false;

	/**
	 * View constructor.
	 *
	 * @param string|null $dir
	 * @param string|null $ext
	 * @param bool $debug
	 */
	private function __construct($dir = null, $ext = null, $debug = true) {
		static::$templatesDirs = is_null($dir) ? dirname(__DIR__).static::$templatesDirs : $dir;
		static::$templatesExtension = is_null($ext) ? static::$templatesDirs : $ext;
		static::$debug = $debug;
		static::initialize();
	}

	/**
     * Twig_Loader_Filesystem loads templates from the file system.
     * This loader can find templates in folders on the file system and is the preferred way to load them.
     *
     * @param string|null $loader_dir
     * @param string|null $cache_dir
     */
    private static function initialize($loader_dir = null, $cache_dir = null)
    {
	    if (true === static::$initialized)
		    throw new \RuntimeException('Error: template-engine had been already initialized.');

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
    public static function render($slug = 'index', $parameters = array(), $extension = null)
    {
	    static::initialize();
	    echo static::$templatesDirs;
	    exit;
	    $ext = empty($extension) ? static::$templatesExtension : $extension;
        return static::$environment->render($slug.'.'.$ext, $parameters);
    }
}
