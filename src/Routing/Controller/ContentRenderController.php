<?php

namespace App\Routing\Controller;

use App\Model;
use App\View;
use Symfony\Component\HttpFoundation;

/**
 * Class Controller.
 */
class ContentRenderController
{
    /**
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param int                    $expiry_minutes
     *
     * @return HttpFoundation\Response
     */
    public static function renderAction(HttpFoundation\Request $request, $expiry_minutes = 1)
    {
        if ($request->getMethod() === 'POST') {
            static::postRenderAction($request);
        }

        list($slug, $arguments) = [$request->get('_route'), $request->get('arguments')];

        /** @var HttpFoundation\Response $response */
        $response = new HttpFoundation\Response(View::render($slug, $arguments), 200);

        // avoid one of the most widespread Internet security issue, XSS (Cross-Site Scripting)
        $response->headers->set('Content-Type', 'text/html');

        // configure the HTTP cache headers
        $response->setMaxAge($expiry_minutes * 60);

        // return response object back
        return $response;
    }

    /**
     * @param HttpFoundation\Request $request
     *
     * @return HttpFoundation\Response
     */
    public static function postRenderAction(HttpFoundation\Request $request)
    {
        $postData = $request->request->all();
        $manager = new Model\MapModel();
        $manager->checkCredentials($postData);
    }
}
