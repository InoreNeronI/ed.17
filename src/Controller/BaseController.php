<?php

namespace App\Controller;

use App\Controller;
use App\Handler;
use App\Helper;
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
    private static function isLocalAdmin(array $args)
    {
        return isset($args['studentCode']) && $args['studentCode'] === \defDb::adminUsername() && isset($args['studentPassword']) && $args['studentPassword'] === \defDb::adminPassword();
    }

    /**
     * @param array $args
     *
     * @return array
     */
    private static function authorize(array $args)
    {
        $path = static::isLocalAdmin($args) ? str_replace('%kernel.root_dir%', ROOT_DIR.'/app', \defDb::dbLocal()['path']) : false;
        if ($path && $path = realpath($path)) {
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
        if ($request->isXmlHttpRequest() && $request->getMethod() === 'POST') {
            $return = [];
            foreach ($request->files as $file) {
                $document = new Handler\Document\Document();
                $document::setUploadDirectory('C:\Users\Administrator\Downloads\_lana\legacy');
                $document->setFile($file);
                $return[] = $document->processFile();
            }

            return HttpFoundation\JsonResponse::create($return);
        }
        $data = $this->getData($request);
        $route = $request->get('_route');
        if ($route === 'boarding' && !isset($data['code'])) {
            $route = 'import';
            $messages = Helper\TranslationsHelper::localize(parseConfig(ROOT_DIR.\def::paths()['translations_dir'].'/page', $route), [], $this->langISOCodes);
            $data = array_merge($data, $messages);
        } elseif ($route === 'boarding' && strpos($data['code'], 'simul') !== false) {
            $route = 'onboard';
            $messages = Helper\TranslationsHelper::localize(parseConfig(ROOT_DIR.\def::paths()['translations_dir'].'/page', $route), $data, $this->langISOCodes);
            $request = HttpFoundation\Request::create(null, $request->getMethod(), array_merge($request->request->all(), $messages, ['flabel' => 'Simul']));
            $data = $this->getSplitPageData($request);
        }
        $view = Handler\ViewHandler::render($route, $data);

        return static::processView($view, $expiryMinutes);
    }

    /**
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param string|int             $page
     * @param int                    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    public function pageRenderAction(HttpFoundation\Request $request, $page = 0, $expiryMinutes = 1)
    {
        $data = $this->getSplitPageData($request, $page);
        $view = Handler\ViewHandler::render($request->get('_route'), $data);

        return static::processView($view, $expiryMinutes);
    }
}
