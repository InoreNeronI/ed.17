<?php

namespace App;

use Twig_Environment;
use Twig_Loader_Filesystem;

/**
 * Class Template.
 */
class View
{
    /** @var \Twig_Loader_Filesystem */
    private static $loader;

    /** @var \Twig_Environment */
    private static $environment;

    /** @var bool */
    private static $loaded = false;

    /**
     * Construct won't be called inside this class and is uncallable from the outside. This prevents instantiating this class.
     *
     * @url http://stackoverflow.com/a/11576945
     */
    private function __construct()
    {
    }

    /**
     * Twig_Loader_Filesystem loads templates from the file system.
     * This loader can find templates in folders on the file system and is the preferred way to load them.
     *
     * @param string $loader_dir
     * @param string $cache_dir
     * @param bool   $debug
     */
    private static function load($loader_dir, $cache_dir = null, $debug = false)
    {
        if (true === static::$loaded) {
            throw new \RuntimeException('Error: template-engine had been already loaded.');
        }

        is_array($loader_dir) ?: $loader_dir = [$loader_dir];
        static::$loader = new Twig_Loader_Filesystem($loader_dir);
        static::$environment = new Twig_Environment(static::$loader, [
            'cache' => is_null($cache_dir) ? $loader_dir[0].'/cache' : $cache_dir,
            'debug' => $debug,
        ]);
        static::$loaded = true;
    }

    /**
     * Renders a template.
     *
     * @param string       $slug
     * @param array        $variables
     * @param array|string $loader_dir
     * @param string       $cache_dir
     * @param mixed        $ext
     * @param mixed        $debug
     *
     * @return string
     */
    public static function render($slug = 'index', $variables = [], $loader_dir = TEMPLATE_FILES_DIR, $cache_dir = TEMPLATE_CACHE_DIR, $ext = TEMPLATE_EXTENSION, $debug = DEBUG)
    {
        static::load($loader_dir, $cache_dir, $debug);
        /** @var string $renderPath */
        $renderPath = "/$slug.$ext";    // path + slug + extension

        return static::$environment->render($renderPath, $variables);
    }
}
