<?php

namespace AppBundle\Action;

use App\Controller;
use Symfony\Component\DependencyInjection;

/**
 * Trait RenderAction.
 */
trait RenderActionTrait
{
    use Controller\BaseControllerTrait;

    /*private $eventDispatcher;
    private $resolver;
    private $router;*/

    /** @var \Twig_Environment */
    private $twig;

    /**
     * RenderAction constructor.
     *
     * The action is automatically registered as a service and dependencies are autowired.
     * Typehint any service you need, it will be automatically injected.
     *
     * @param DependencyInjection\Container $container
     */
    public function __construct(
        /*EventDispatcher\EventDispatcher $dispatcher,
        HttpKernel\Controller\ControllerResolver $resolver,
        Routing\RouterInterface $router,
        Twig_Environment $twig,*/
        DependencyInjection\Container $container)
    {
        /*$this->eventDispatcher = $dispatcher;
        $this->resolver = $resolver;
        $this->router = $router;
        $this->twig = $twig;*/
        /** @var array $credentials */
        $credentials = [
            'host' => $container->hasParameter('host') ? $container->getParameter('host') : null,
            'user' => $container->hasParameter('user') ? $container->getParameter('user') : null,
            'password' => $container->hasParameter('password') ? $container->getParameter('password') : null,
            'dbname' => $container->hasParameter('dbname') ? $container->getParameter('dbname') : null,
            'driver' => $container->hasParameter('driver') ? $container->getParameter('driver') : null,
        ];
        $container->hasParameter('path') ? $credentials['path'] = $container->getParameter('path') : null;
        $container->hasParameter('port') ? $credentials['port'] = $container->getParameter('port') : null;
        $container->hasParameter('unix_socket') ? $credentials['unix_socket'] = $container->getParameter('unix_socket') : null;
        /* @var array codes */
        $this->codes = $container->getParameter('codes');
        /* @var array langISOCodes */
        $this->langISOCodes = array_unique(array_values($container->getParameter('languages')));
        /* @var array metric */
        $this->metric = $container->getParameter('metric');
        /* @var array targets */
        $this->targets = $container->getParameter('targets');
        /* @var \Twig_Environment twig */
        $this->twig = $container->get('twig');
    }
}
