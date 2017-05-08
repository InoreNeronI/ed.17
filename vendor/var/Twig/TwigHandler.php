<?php

namespace Twig;

use Assetic\Filter;
use Twig;
use Twig_Environment;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;

/**
 * Class TwigHandler
 */
class TwigHandler
{
    /** @var Twig_Loader_Filesystem */
    private static $loader;

    /** @var Twig_Environment */
    private static $twig;

    /** @var bool */
    private static $loaded = false;

    /** @var string */
    private static $uglifyJsWindowsPath = '%APPDATA%\npm\uglifyjs.cmd';

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
        $filter = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? new Filter\UglifyJs2Filter(static::$uglifyJsWindowsPath) : new Filter\UglifyJs2Filter();
        $filter->setMangle(true);
        $filter->setCompress([
            'sequences' => true,
            'dead_code' => true,
            'mangle' => true,
            'conditionals' => true,
            'booleans' => true,
            'unused' => true,
            'if_return' => true,
            'join_vars' => true,
            'drop_console' => true,
        ]);
        static::$twig->addExtension(new Twig\Extension\UglifyExtension(new Twig\Uglifier($filter, true)));
        if ($debug) {
            static::$twig->addExtension(new Twig_Extension_Debug());
        }
        static::$loaded = true;
    }

    /**
     * Renders a template.
     *
     * @param string $slug
     * @param array  $context
     * @param bool   $autoescape
     * @param bool   $strictVariables
     *
     * @return string
     */
    public static function render(
        $slug = 'index',
        $context = [],
        $autoescape = false,
        $strictVariables = true)
    {
        static::load(getenv('TEMPLATE_FILES_DIR'), getenv('TEMPLATE_CACHE_DIR'), $autoescape, getenv('DEBUG'), $strictVariables);

        return static::$twig->render(static::getTemplatePath($slug), $context);
    }

    /**
     * Returns the path of a template.
     *
     * @param string $slug
     *
     * @return string
     */
    public static function getTemplatePath($slug)
    {
        /** @var string $path */
        $path = $slug === 'index' ? '' : '/page';

        return "$path/$slug.html.twig"; // path + slug + extension
    }
}
