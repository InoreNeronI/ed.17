<?php

namespace App\Kernel;

use App\Event\ResponseEvent;
use App\Routing\RouteMap;
use Symfony\Bundle\FrameworkBundle;
use Symfony\Component\Config;
use Symfony\Component\DependencyInjection;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing as BaseRouting;


/**
 * Class Kernel\Micro.
 */
class Micro extends HttpKernel\Kernel
{
    /** @var RouteMap */
    private $routesMap;

    /** @var BaseRouting\Matcher\UrlMatcherInterface */
    private $matcher;

    /** @var HttpKernel\Controller\ControllerResolverInterface */
    private $resolver;

    /** @var EventDispatcher\EventDispatcher */
    private $dispatcher;

    use FrameworkBundle\Kernel\MicroKernelTrait;

    public function registerBundles()
    {
        return array(new FrameworkBundle\FrameworkBundle());
    }

    protected function configureRoutes(BaseRouting\RouteCollectionBuilder $routes)
    {
        $routes->import($this->routesMap);
    }

    protected function configureContainer(DependencyInjection\ContainerBuilder $c, Config\Loader\LoaderInterface $loader)
    {
        // framework.secret est le seul paramètre obligatoire pour le framework
        $c->loadFromExtension('framework', ['secret' => '12345']);
    }

    /**
     * MicroKernel constructor.
     */
    public function __construct()
    {
        parent::__construct(DEBUG ? 'dev' : 'prod', DEBUG);

        /** @var RouteMap $routesMap */
        $this->routesMap = new RouteMap();
    }

    /**
     * Handles a request.
     * Our handle method takes an HTTP Request object, the Symfony Http Kernel so we have access to the master request, and a catch flag (we'll see why below).
     *
     * @param HttpFoundation\Request $request
     * @param int                    $type
     * @param bool                   $catch
     *
     * @return HttpFoundation\Response
     */
    public function handle(HttpFoundation\Request $request, $type = HttpKernel\HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        /** @var BaseRouting\Matcher\UrlMatcher $matcher */
        $this->matcher = $this->routesMap->getMatcher();

        /** @var HttpKernel\Controller\ControllerResolver $resolver */
        $this->resolver = new HttpKernel\Controller\ControllerResolver();

        /** @var EventDispatcher\EventDispatcher $dispatcher */
        $this->dispatcher = new EventDispatcher\EventDispatcher();
        $this->dispatcher->addSubscriber(new HttpKernel\EventListener\RouterListener($this->matcher, new HttpFoundation\RequestStack()));

        // Feed the RequestContext
        $this->matcher->getContext()->fromRequest($request);

        // Next we take our HTTP request object and see if our Request contains a routing match (see our routes class below for a match)
        try {
            $request->attributes->add($this->matcher->match($request->getPathInfo()));

            // Our request found a match so let's use the Controller Resolver to resolve our controller.
            /** @var callable|false $controller */
            $controller = $this->resolver->getController($request);

            // Pass our request arguments as an argument to our resolved controller (see controller below). If you have form data, the resolver's
            // 'getArguments' method's functionality will parse that data for you and then pass it as an array to your controller.
            /** @var array $arguments */
            $arguments = $this->resolver->getArguments($request, $controller);

            // Invoke the name of the controller that is resolved from a match in our routing class
            /** @var HttpFoundation\Response $response */
            $response = call_user_func_array($controller, $arguments);

        } catch (BaseRouting\Exception\ResourceNotFoundException $e) {
            // No such route exception return a 404 response
            $response = new HttpFoundation\Response(sprintf('Resource not found: %s', $e->getMessage()), 404);

        } catch (\Exception $e) {
            $msg = $e->getMessage();
            // Something blew up exception return a 500 response
            $response = new HttpFoundation\Response(sprintf('An error occurred: %s', empty($msg) ? $e->getTraceAsString() : $msg), 500);
        }

        // The dispatcher, the central object of the event dispatcher system, notifies listeners of an event dispatched to it.
        // Put another way: your code dispatches an event to the dispatcher, the dispatcher notifies all registered listeners for the event, and each listener do whatever it wants with the event.
        $this->dispatcher->dispatch('response', new ResponseEvent($response, $request));

        return $response;
    }
}
