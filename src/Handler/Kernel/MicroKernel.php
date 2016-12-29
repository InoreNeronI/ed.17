<?php

namespace App\Handler\Kernel;

use App\Handler;
use Symfony\Bundle\FrameworkBundle;
use Symfony\Bundle\WebProfilerBundle;
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
    public function __construct($debug)
    {
        parent::__construct($debug ? 'dev' : 'prod', $debug);
    }

    /**
     * @return array
     */
    public function registerBundles()
    {
        $bundles = [new FrameworkBundle\FrameworkBundle()];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new WebProfilerBundle\WebProfilerBundle();
        }

        return $bundles;
    }

    /**
     * @param Routing\RouteCollectionBuilder $routes
     */
    protected function configureRoutes(Routing\RouteCollectionBuilder $routes)
    {
        $routes->import($this->routesMap);

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $routes->mount('/_wdt', $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml'));
            $routes->mount('/_profiler', $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml'));
        }
    }

    /**
     * @param DependencyInjection\ContainerBuilder $c
     * @param Config\Loader\LoaderInterface        $loader
     */
    protected function configureContainer(DependencyInjection\ContainerBuilder $c, Config\Loader\LoaderInterface $loader)
    {
        // load bundles' configuration
        // framework.secret est le seul paramètre obligatoire pour le framework
        $c->loadFromExtension('framework', [
            'secret' => '12345',
            'profiler' => null,
        ]);
        $c->loadFromExtension('web_profiler', ['toolbar' => true]);

        // add configuration parameters
//        $c->setParameter('mail_sender', 'user@example.com');

        // register services
//        $c->register('app.markdown', 'AppBundle\\Service\\Parser\\Markdown');
    }
}
