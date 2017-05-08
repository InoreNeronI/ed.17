<?php

namespace Kernel;

use Handler;
use Kernel;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;

/**
 * Class KernelBase
 */
class KernelBase implements HttpKernel\HttpKernelInterface
{
    use Kernel\KernelTrait;

    /**
     * KernelBase constructor.
     *
     * @param bool|string $debug
     */
    public function __construct($debug)
    {
        static::$baseUrl = \def::homePath();
        static::$baseSlug = \def::homeSlug();
        static::$debug = (bool) $debug;

        /* @var EventDispatcher\EventDispatcher $dispatcher */
        $this->dispatcher = new EventDispatcher\EventDispatcher();

        /* @var HttpKernel\Controller\ControllerResolver $resolver */
        $this->resolver = new HttpKernel\Controller\ControllerResolver();

        /* @var Handler\Router $routing */
        $routing = new Handler\Router(\def::routes(), getenv('TRANSLATIONS_DIR'), static::$baseUrl, static::$baseSlug);

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
            parseConfig(getenv('TRANSLATIONS_DIR'), 'layout'),
            parseConfig(getenv('TRANSLATIONS_DIR').'/page', is_null($slug) ? static::$baseSlug : $slug),
            isset(static::$headers['ErrorData']) ? ['ErrorData' => static::$headers['ErrorData']] : []
        );
    }
}
