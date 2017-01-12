<?php

namespace App\Handler;

use RuntimeException;
use Twig_Environment;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;

/**
 * Class ViewHandler.
 */
class ViewHandler
{
    /** @var Twig_Loader_Filesystem */
    private static $loader;

    /** @var Twig_Environment */
    private static $twig;

    /** @var bool */
    private static $loaded = false;

    /**
     * Construct won't be called inside this class and is uncallable from the outside. This prevents instantiating this class.
     *
     * @see http://stackoverflow.com/a/11576945
     */
    private function __construct()
    {
    }

    /**
     * Twig_Loader_Filesystem loads templates from the file system.
     * This loader can find templates in folders on the file system and is the preferred way to load them.
     *
     * @param string $loaderDir
     * @param bool   $autoescape
     * @param null   $cacheDir
     * @param bool   $debug
     * @param bool   $strictVariables
     * @param string $namespace
     */
    private static function load($loaderDir, $cacheDir = null, $autoescape = false, $debug = false, $strictVariables = true, $namespace = 'App')
    {
        if (true === static::$loaded) {
            //throw new RuntimeException('Error: template-engine had been already loaded.');
            return;
        }

        static::$loader = new Twig_Loader_Filesystem($loaderDir);
        static::$loader->addPath($loaderDir, $namespace);
        static::$twig = new Twig_Environment(static::$loader, [
            'autoescape' => $autoescape,
            'cache' => is_null($cacheDir) ? $loaderDir[0].'/cache' : $cacheDir,
            'debug' => $debug,
            'strict_variables' => $strictVariables,
        ]);
        if ($debug) {
            static::$twig->addExtension(new Twig_Extension_Debug());
        }
        static::$loaded = true;
    }

    /**
     * Renders a template.
     *
     * @param string       $slug
     * @param array        $context
     * @param array|string $loaderDir
     * @param string       $cacheDir
     * @param bool         $autoescape
     * @param bool         $debug
     * @param bool         $strictVariables
     *
     * @return string
     */
    public static function render(
        $slug = 'index',
        $context = [],
        $loaderDir = TEMPLATE_FILES_DIR,
        $cacheDir = TEMPLATE_CACHE_DIR,
        $autoescape = false,
        $debug = DEBUG,
        $strictVariables = true)
    {
        static::load($loaderDir, $cacheDir, $autoescape, $debug, $strictVariables);

        return static::$twig->render(static::getTemplatePath($slug), $context);
    }

    /**
     * Returns the path of a template.
     *
     * @param $slug
     * @param $ext
     *
     * @return string
     */
    public static function getTemplatePath($slug, $ext = TEMPLATE_EXTENSION)
    {
        /** @var string $path */
        $path = $slug === 'index' ? '' : '/page';

        return "$path/$slug.$ext";   // path + slug + extension
    }
}
