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
     * @param string|null            $page
     * @param int                    $expiry_minutes
     *
     * @return HttpFoundation\Response
     */
    public static function renderAction(HttpFoundation\Request $request, $page = null, $expiry_minutes = 1)
    {
        list($slug, $messages) = [$request->get('_route'), $request->get('messages')];

        if (strpos($slug, 'canvas') !== false) {
            /** @var array $texts */
            $texts = static::processSplitPanel($request, $page, $messages);
            $slug = 'canvas';
        } else {
            /** @var array $texts */
            $texts = static::processRender($request, $messages);
        }
        dump($texts);
        /** @var string $content */
        $content = View::render($slug, $texts);

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
    private static function processRender(HttpFoundation\Request $request, array $messages)
    {
        if ($request->getMethod() === 'POST') {
            $manager = new Model\StudentModel();
            $access_data = $manager->checkCredentials($request->request->all());
            $messages = $manager::localizeMessages(array_merge($messages, $access_data));

            return array_merge($access_data, $messages, ['target' => \def::dbTables()[$access_data['table']]]);
        } elseif ($request->getMethod() === 'GET') {
            return Model\StudentModel::localizeMessages($messages);
        }
    }

    /**
     * @param HttpFoundation\Request $request
     * @param string                 $page
     * @param array                  $messages
     *
     * @return array|false
     */
    private static function processSplitPanel(HttpFoundation\Request $request, $page, array $messages)
    {
        if ($request->getMethod() === 'POST') {
            $params = $request->request->all();
            isset($params['lang']) ?: $params['lang'] = isset($messages['lang']) ? $messages['lang'] : $request->getLocale();
            $manager = new Model\StudentModel();

            return $manager->loadPageData($params, sprintf('%02d', intval($page)));
        } elseif ($request->getMethod() === 'GET') {
            return [];
        }
    }
}
