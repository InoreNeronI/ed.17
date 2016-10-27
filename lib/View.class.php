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

	/**
	 * Construct won't be called inside this class and is uncallable from the outside. This prevents instantiating this class.
	 * This is by purpose, because we want a static class.
	 *
	 * @url http://stackoverflow.com/a/11576945
	 */
	private function __construct() {}

	/**
     * Twig_Loader_Filesystem loads templates from the file system.
     * This loader can find templates in folders on the file system and is the preferred way to load them.
     *
     * @param string|null $loader_dir
     * @param string|null $cache_dir
	 * @param string|null $ext
	 * @param bool $debug
     */
    private static function initialize($loader_dir = null, $cache_dir = null, $ext = null, $debug = true)
    {
	    if (true === static::$initialized)
		    throw new \RuntimeException('Error: template-engine had been already initialized.');

	    static::$templatesDirs = [dirname(__DIR__).static::$templatesDirs];
	    empty($loader_dir) ?: static::$templatesDirs[] = $loader_dir;
	    empty($ext) ?: static::$templatesExtension = $ext;
        static::$loader = new Twig_Loader_Filesystem(static::$templatesDirs);
        static::$environment = new Twig_Environment(static::$loader, array(
            'cache' => is_null($cache_dir) ? static::$templatesDirs[0].'/cache' : $cache_dir,
            'debug' => $debug
        ));
        static::$initialized = true;
    }

	/**
	 * Renders a template.
	 *
	 * @param string $slug
	 * @param array $variables
	 * @param string $extension
	 *
	 * @return string
	 */
    public static function render($slug = 'index', $variables = array(), $extension = null)
    {
	    static::initialize();
	    empty($extension) ?: static::$templatesExtension = $extension;
        return static::$environment->render($slug.'.'.static::$templatesExtension, $variables);
    }
}
