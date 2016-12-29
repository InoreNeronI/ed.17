<?php

namespace App\Handler\Kernel;

use App\Handler;
use Symfony\Bundle\FrameworkBundle;
use Symfony\Component\Config;
use Symfony\Component\DependencyInjection;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;

/**
 * Class Kernel\Micro.
 */
class MicroKernel extends HttpKernel\Kernel implements HttpKernel\HttpKernelInterface
{
    use Handler\Kernel\NanoKernelTrait;
    use FrameworkBundle\Kernel\MicroKernelTrait;

    /**
     * Kernel\Micro constructor.
     *
     * @param bool|string $debug
     */
    public function __construct($debug = DEBUG)
    {
        parent::__construct(DEBUG ? 'dev' : 'prod', DEBUG);
    }

    /**
     * @return array
     */
    public function registerBundles()
    {
        return [new FrameworkBundle\FrameworkBundle()];
    }

    /**
     * @param Routing\RouteCollectionBuilder $routes
     */
    protected function configureRoutes(Routing\RouteCollectionBuilder $routes)
    {
        $routes->import($this->routesMap);
    }

    /**
     * @param DependencyInjection\ContainerBuilder $c
     * @param Config\Loader\LoaderInterface        $loader
     */
    protected function configureContainer(DependencyInjection\ContainerBuilder $c, Config\Loader\LoaderInterface $loader)
    {
        // framework.secret est le seul paramètre obligatoire pour le framework
        $c->loadFromExtension('framework', ['secret' => '12345']);
    }
}
