<?php

namespace AppBundle\Action;

use AppBundle;
use Symfony\Component\HttpFoundation;

/**
 * Class PageRenderAction.
 */
final class PageRenderAction
{
    use AppBundle\Action\RenderActionTrait;

    /**
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param string|null            $page
     * @param int                    $expiryMinutes
     * @param string                 $templateExtension
     * @param string                 $templateNamespace
     *
     * @return HttpFoundation\Response
     */
    public function __invoke(HttpFoundation\Request $request, $page = null, $expiryMinutes = 1, $templateExtension = 'html.twig', $templateNamespace = 'App')
    {
        /** @var string $slug */
        $slug = $request->get('_route');
        /** @var string $path */
        $path = $slug === 'index' ? '' : '/page';
        /** @var string $name */    // @ + namespace + path + slug + extension
        $name = "@$templateNamespace$path/$slug.$templateExtension";
        /** @var array $render */
        $render = $this->getSplitPageData($request, $page);

        return new HttpFoundation\Response($this->twig->render($name, $render));
    }
}
