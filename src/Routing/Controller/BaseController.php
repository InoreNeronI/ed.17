<?php

namespace App\Routing\Controller;

use App\Model;
use App\View;
use Symfony\Component\HttpFoundation;

/**
 * Class BaseController.
 */
class BaseController
{
    /**
     * Generates a response from the given request object.
     *
     * @param string $slug
     * @param array  $texts
     * @param int    $expiry_minutes
     *
     * @return HttpFoundation\Response
     */
    protected static function processRender($slug = 'index', $texts = [], $expiry_minutes = 1)
    {
        //dump($texts);
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
    protected static function getRender(HttpFoundation\Request $request, array $messages)
    {
        $data = [];
        if ($request->getMethod() === 'POST') {
            $manager = new Model\CredentialsModel();
            $data = $manager->checkCredentials($request->request->all());
            $messages = array_merge($messages, ['target' => \def::dbTables()[$data['table']]]);
        }

        return Model\TranslationsModel::localize($messages, $data, $request->getLocale());
    }

    /**
     * @param HttpFoundation\Request $request
     * @param string                 $page
     * @param array                  $messages
     *
     * @return array
     */
    protected static function getSplitPageRender(HttpFoundation\Request $request, $page, array $messages)
    {
        if ($request->getMethod() === 'POST') {
            $manager = new Model\PagesModel();
            $data = $request->request->all();
            $args = Model\TranslationsModel::localize($messages, $data, $request->getLocale());

            return $manager->loadPageData($args, sprintf('%02d', intval($page)));
        }

        return [];
    }
}
