<?php

namespace App\Routing\Controller;

use Symfony\Component\HttpFoundation;

/**
 * Class ContentRenderController.
 */
class ContentRenderController extends BaseController
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
        try {
            //dump($request->get)
            list($slug, $render) = [$request->get('_route'), static::getRender($request, $request->get('messages'))];
        } catch (\Exception $e) {
            $session = new HttpFoundation\Session\Session();
            static::getFlashMessages($session, strtolower($e->getMessage()));

            return HttpFoundation\RedirectResponse::create('/', 302, [/*'error' => $msg*/]);
        }

        return static::processRender($slug, $render, $expiry_minutes);
    }

    /**
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param string|null            $page
     * @param int                    $expiry_minutes
     *
     * @return HttpFoundation\Response
     */
    public static function pageAction(HttpFoundation\Request $request, $page = null, $expiry_minutes = 1)
    {
        list($slug, $render) = [$request->get('_route'), static::getSplitPageRender($request, $page, $request->get('messages'))];

        return static::processRender($slug, $render, $expiry_minutes);
    }
}
