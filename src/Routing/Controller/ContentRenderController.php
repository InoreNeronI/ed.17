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
        list($slug, $arguments) = [$request->get('_route'), $request->get('arguments')];

        /** @var array $messages */
        $messages = static::processRender($request, $arguments);

        /** @var string $content */
        $content = View::render($slug, $messages);

        /** @var HttpFoundation\Response $response */
        $response = new HttpFoundation\Response($content, 200);

        // avoid one of the most widespread Internet security issue, XSS (Cross-Site Scripting)
        $response->headers->set('Content-Type', 'text/html');

        // configure the HTTP cache headers
        $response->setMaxAge($expiry_minutes * 60);

        // return response object back
        return $response;
    }

    /**
     * @param HttpFoundation\Request $request
     * @param array                  $messages
     *
     * @return array
     */
    public static function processRender(HttpFoundation\Request $request, array $messages)
    {
        if ($request->getMethod() === 'POST') {
            $manager = new Model\StudentModel();
            $access_data = $manager->checkCredentials($request->request->all());
            $messages = $manager::localizeMessages($messages, $access_data['lang'], $access_data['table']);

            return array_merge($access_data, $messages);
        } elseif ($request->getMethod() === 'GET') {
            return Model\StudentModel::localizeMessages($messages);
        }
    }
}
