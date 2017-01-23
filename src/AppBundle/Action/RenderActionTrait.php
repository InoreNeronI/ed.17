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
    private $router;
    private $twig;*/

    /** @var DependencyInjection\Container */
    private $container;

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
        /* @var array codes */
        $this->codes = $container->getParameter('codes');
        /* @var array langISOCodes */
        $this->langISOCodes = array_unique(array_values($container->getParameter('languages')));
        /* @var array metric */
        $this->metric = $container->getParameter('metric');
        /* @var array targets */
        $this->targets = $container->getParameter('targets');
        /** @var array $args */
        /*$args = [
            'host' => $container->hasParameter('host') ? $container->getParameter('host') : null,
            'user' => $container->hasParameter('user') ? $container->getParameter('user') : null,
            'password' => $container->hasParameter('password') ? $container->getParameter('password') : null,
            'dbname' => $container->hasParameter('dbname') ? $container->getParameter('dbname') : null,
            'driver' => $container->hasParameter('driver') ? $container->getParameter('driver') : null,
        ];
        $container->hasParameter('path') ? $args['path'] = $container->getParameter('path') : null;
        $container->hasParameter('port') ? $args['port'] = $container->getParameter('port') : null;
        $container->hasParameter('unix_socket') ? $args['unix_socket'] = $container->getParameter('unix_socket') : null;*/
        /** @var DependencyInjection\Container $container */
        $this->container = $container;
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    private function isAdmin(array $args)
    {
        return isset($args['fcodalued']) && $args['fcodalued'] === $this->container->getParameter('users.local.admin.name') && isset($args['code']) && $args['code'] === $this->container->getParameter('users.local.admin.pw');
    }

    /**
     * @param array $args
     *
     * @return array
     */
    private function authorize(array $args)
    {
        return array_merge($args, $this->container->getParameter($this->isAdmin($args) ? 'connections.local' : 'connections.dist'));
    }
}
