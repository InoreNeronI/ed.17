<?php

namespace AppBundle\Action;

use AppBundle;
use Symfony\Component\HttpFoundation;

/**
 * Class RenderAction.
 */
final class RenderAction
{
    use AppBundle\Action\RenderActionTrait;

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
     * @param string                 $templateNamespace
     *
     * @return HttpFoundation\Response
     */
    public function __invoke(HttpFoundation\Request $request, $expiryMinutes = 1, $templateExtension = 'html.twig', $templateNamespace = 'App')
    {
        /** @var string $slug */
        $slug = $request->get('_route');
        /** @var string $path */
        $path = $slug === 'index' ? '' : '/page';
        /** @var string $name */    // @ + namespace + path + slug + extension
        $name = "@$templateNamespace$path/$slug.$templateExtension";
        /** @var array $render */
        $render = $this->getData($request);

        return new HttpFoundation\Response($this->twig->render($name, $render));
    }
}
