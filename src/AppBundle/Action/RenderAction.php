<?php

namespace AppBundle\Action;

use App\Controller;
use Symfony\Component\DependencyInjection;
use Symfony\Component\HttpFoundation;

/**
 * Class RenderAction.
 */
final class RenderAction
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
        /* @var array authorization */
        $this->authorization = [
            'database_host' => $container->getParameter('database_host'),
            'database_user' => $container->getParameter('database_user'),
            'database_password' => $container->getParameter('database_password'),
            'database_name' => $container->getParameter('database_name'),
            'database_driver' => $container->getParameter('database_driver'),
            'options' => $container->hasParameter('database_port') ? ['port' => $container->getParameter('database_port')] : [],
        ];
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

    /**
     * Generates a response from the given request object.
     *
     * @Route("/myaction", name="my_action")
     * Using annotations is not mandatory, XML and YAML configuration files can be used instead.
     * If you want to decouple your actions from the framework, don't use annotations.
     *
     * @param HttpFoundation\Request $request
     * @param int                    $expiryMinutes
     * @param string                 $templateExtension
     * @param string                 $namespace
     *
     * @return HttpFoundation\Response
     */
    public function __invoke(HttpFoundation\Request $request, $expiryMinutes = 1, $templateExtension = 'html.twig', $namespace = 'App')
    {
        /** @var array $render */
        $render = $this->getRender($request, $this->renderArguments());
        /** @var string $slug */
        $slug = $request->get('_route');
        /** @var string $path */
        $path = $slug === 'index' ? '' : '/page';
        /** @var string $name */
        $name = "$path/$slug.$templateExtension";   // path + slug + extension

        return new HttpFoundation\Response($this->twig->render('@'.$namespace.$name, $render));
    }
}
