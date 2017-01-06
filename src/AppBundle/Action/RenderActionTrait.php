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
            'database_host' => $container->hasParameter('database_host') ? $container->getParameter('database_host') : null,
            'database_user' => $container->hasParameter('database_user') ? $container->getParameter('database_user') : null,
            'database_password' => $container->hasParameter('database_password') ? $container->getParameter('database_password') : null,
            'database_name' => $container->hasParameter('database_name') ? $container->getParameter('database_name') : null,
            'database_driver' => $container->hasParameter('database_driver') ? $container->getParameter('database_driver') : null,
        ];
        $container->hasParameter('database_path') ? $credentials['database_path'] = $container->getParameter('database_path') : null;
        $container->hasParameter('database_port') ? $credentials['database_port'] = $container->getParameter('database_port') : null;
        $container->hasParameter('database_socket') ? $credentials['database_socket'] = $container->getParameter('database_socket') : null;
        /* @var array authorization */
        $this->authorization = $this->renderAuthorization($credentials);
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
