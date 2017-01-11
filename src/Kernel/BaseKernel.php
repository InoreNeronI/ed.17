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
        $this->baseUrl = \def::routes()['home_url_path'];
        $this->baseSlug = \def::routes()['home_url_slug'];

        /* @var EventDispatcher\EventDispatcher $dispatcher */
        $this->dispatcher = new EventDispatcher\EventDispatcher();

        /* @var HttpKernel\Controller\ControllerResolver $resolver */
        $this->resolver = new HttpKernel\Controller\ControllerResolver();

        /* @var Handler\RouteHandler $routing */
        $routing = new Handler\RouteHandler(\def::routes()['routes'], ROOT_DIR.\def::paths()['translations_dir'], $this->baseUrl, $this->baseSlug);

        /* @var Routing\RequestContext $context */
        $context = new Routing\RequestContext();

        /* @var Routing\Matcher\UrlMatcher $matcher */
        $this->matcher = new Routing\Matcher\UrlMatcher($routing->getRoutes(), $context);
    }
}
