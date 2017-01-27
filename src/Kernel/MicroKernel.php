<?php

namespace App\Kernel;

use App\Handler;
use AppBundle;
use Dunglas\ActionBundle;
use Symfony\Bundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle;
use Symfony\Component\Config;
use Symfony\Component\DependencyInjection;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;
use Symfony\Component\Yaml;

/**
 * Class Kernel\Micro.
 */
class MicroKernel extends HttpKernel\Kernel implements HttpKernel\HttpKernelInterface
{
    /** @var array $paths */
    private $paths;

    /** @var array $routes */
    private $routes;

    use FrameworkBundle\Kernel\MicroKernelTrait;

    /**
     * Kernel\Micro constructor.
     *
     * @param bool|string $debug
     */
    public function __construct($debug)
    {
        parent::__construct($debug ? 'dev' : 'prod', $debug);

        /* @var array $paths */
        $this->paths = Yaml\Yaml::parse(file_get_contents(ROOT_DIR.'/app/config/paths.yml'))['paths'];

        /* @var array $routes */
        $this->routes = Yaml\Yaml::parse(file_get_contents(ROOT_DIR.'/app/config/routes.yml'));
    }

    public function getRootDir()
    {
        return ROOT_DIR.'/app';
    }

    public function getCacheDir()
    {
        return ROOT_DIR.$this->paths['cache_dir'].'/'.$this->environment;
    }

    public function getLogDir()
    {
        return ROOT_DIR.$this->paths['logs_dir'].'/'.$this->environment;
    }

    /**
     * @return array
     */
    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle\FrameworkBundle(),
            new TwigBundle\TwigBundle(),
            new ActionBundle\DunglasActionBundle(),
            new AppBundle\AppBundle(), ];

        if (in_array($this->getEnvironment(), ['dev'/*, 'test'*/], true)) {
            $bundles[] = new WebProfilerBundle\WebProfilerBundle();
        }

        return $bundles;
    }

    /**
     * @param DependencyInjection\ContainerBuilder $container
     * @param Config\Loader\LoaderInterface        $loader
     */
    protected function configureContainer(DependencyInjection\ContainerBuilder $container, Config\Loader\LoaderInterface $loader)
    {
        $loader->load($this->getRootDir().'/config/config.yml');
        $loader->load('@AppBundle/Resources/config/config_'.$this->getEnvironment().'.yml');
        //$container->setParameter('paths', $this->paths);
        // load bundles' configuration
        // framework.secret est le seul paramètre obligatoire pour le framework
        //$container->loadFromExtension('framework', [
        //    'profiler' => ['enabled' => DEBUG],
        //    'secret' => '12345',
        //]);
        //$container->loadFromExtension('web_profiler', ['toolbar' => true]);
        //dump($c);exit;
        //$loader->load(ROOT_DIR.'/app/config/config_'.$this->getEnvironment().'.yml');
        //$loader->load(ROOT_DIR.'/app/config/services.yml');
        // add configuration parameters
//        $container->setParameter('mail_sender', 'user@example.com');
        // register services
    //    $container->register('twig', 'Twig_Environment');
//        $container->register('app.markdown', 'AppBundle\\Service\\Parser\\Markdown');
        //$container->register('app.default_controller', 'AppBundle\\Controller\\AppController');
//        if (in_array($this->getEnvironment(), ['dev'/*, 'test'*/], true)) {
//            $vendorDir = '%kernel.root_dir%'.sprintf('/../vendor%s/', TURBO ? '-tiny' : null);
//            $container->loadFromExtension('twig', array(
//                'paths' => array(
//                    $vendorDir.'acme/foo-bar/templates' => 'foo_bar',
//                )
//            ));
//        }
    }

    /**
     * @param Routing\RouteCollectionBuilder $routes
     */
    protected function configureRoutes(Routing\RouteCollectionBuilder $routes)
    {
        /* @var Handler\RouteHandler $routing */
        $routing = new Handler\RouteHandler(
            $this->routes['routes'],
            ROOT_DIR.$this->paths['translations_dir'],
            $this->routes['home_url_path'],
            $this->routes['home_url_slug']);

        foreach ($routing->getRoutes()->all() as $name => $route) {
            $routes->addRoute($route, $name);
        }

        // import the WebProfilerRoutes, only if the bundle is enabled
        if (isset($this->bundles['WebProfilerBundle'])) {
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml', '/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml', '/_profiler');
        }

        // load the annotation routes
        //$routes->import(__DIR__.'/../src/App/Controller/', '/', 'annotation');
    }
}
