<?php

namespace App\Controller;

use App\Controller;
use App\Handler;
use Symfony\Component\HttpFoundation;

/**
 * Class BaseController.
 */
class BaseController
{
    use Controller\BaseControllerTrait;

    /**
     * BaseController constructor.
     */
    public function __construct()
    {
        /* @var array codes */
        $this->codes = \def::dbCodes();
        /* @var array langISOCodes */
        $this->langISOCodes = \def::langISOCodes();
        /* @var array metric */
        $this->metric = \def::metric();
        /* @var array targets */
        $this->targets = \def::dbTargets();
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    private static function isAdmin(array $args)
    {
        return isset($args['fcodalued']) && $args['fcodalued'] === \defDb::adminUsername() && isset($args['code']) && $args['code'] === \defDb::adminPassword();
    }

    /**
     * @param array $args
     *
     * @return array
     */
    private static function authorize(array $args)
    {
        if ($path = static::isAdmin($args) ? str_replace('%kernel.root_dir%', ROOT_DIR.'/app', \defDb::dbLocal()['path']) : false) {
            return array_merge($args, \defDb::dbLocal(), ['path' => $path]);
        }

        return array_merge($args, \defDb::dbDist());
    }

    /**
     * Generates a response from the given content.
     *
     * @param string $content
     * @param int    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    private static function processView($content = 'Hello World!', $expiryMinutes = 1)
    {
        /** @var HttpFoundation\Response $response */
        $response = new HttpFoundation\Response($content, 200);

        // avoid one of the most widespread Internet security issue, XSS (Cross-Site Scripting)
        $response->headers->set('Content-Type', 'text/html');

        // compression
        $response->headers->set('Accept-Encoding', 'gzip, deflate');

        // configure the HTTP cache headers
        $response->setMaxAge($expiryMinutes * 60);

        // return response object back
        return $response;
    }

    /**
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param int                    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    public function renderAction(HttpFoundation\Request $request, $expiryMinutes = 1)
    {
        $data = $this->getData($request);
        $view = Handler\ViewHandler::render($request->get('_route'), $data);

        return static::processView($view, $expiryMinutes);
    }

    /**
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param string|null            $page
     * @param int                    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    public function pageRenderAction(HttpFoundation\Request $request, $page, $expiryMinutes = 1)
    {
        $data = $this->getSplitPageData($request, $page);
        $view = Handler\ViewHandler::render($request->get('_route'), $data);

        return static::processView($view, $expiryMinutes);
    }
}
