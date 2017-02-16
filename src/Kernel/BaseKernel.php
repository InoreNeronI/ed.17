<?php

namespace App\Kernel;

use App\Handler;
use App\Kernel;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;

/**
 * Class Kernel\Base.
 */
class BaseKernel implements HttpKernel\HttpKernelInterface
{
    use Kernel\BaseKernelTrait;

    /**
     * Kernel\Base constructor.
     *
     * @param bool|string $debug
     */
    public function __construct($debug)
    {
        static::$baseUrl = \def::routes()['home_url_path'];
        static::$baseSlug = \def::routes()['home_url_slug'];
        static::$debug = (bool) $debug;

        /* @var EventDispatcher\EventDispatcher $dispatcher */
        $this->dispatcher = new EventDispatcher\EventDispatcher();

        /* @var HttpKernel\Controller\ControllerResolver $resolver */
        $this->resolver = new HttpKernel\Controller\ControllerResolver();

        /* @var Handler\RouteHandler $routing */
        $routing = new Handler\RouteHandler(\def::routes()['routes'], ROOT_DIR.\def::paths()['translations_dir'], static::$baseUrl, static::$baseSlug);

        /* @var Routing\RequestContext $context */
        $context = new Routing\RequestContext();

        /* @var Routing\Matcher\UrlMatcher $matcher */
        $this->matcher = new Routing\Matcher\UrlMatcher($routing->getRoutes(), $context);
    }

    /**
     * @param string|null $slug
     *
     * @return array
     */
    private static function getMessages($slug = null)
    {
        return array_merge(
            parseConfig(ROOT_DIR.\def::paths()['translations_dir'], 'layout'),
            parseConfig(ROOT_DIR.\def::paths()['translations_dir'].'/page', is_null($slug) ? static::$baseSlug : $slug),
            isset(static::$headers['ErrorData']) ? ['ErrorData' => static::$headers['ErrorData']] : []
        );
    }
}
