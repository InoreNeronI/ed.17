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
        /* @var array authorization */
        $this->authorization = \def::dbCredentials();
        if (in_array('database_port', $this->authorization)) {
            array_merge($this->authorization, ['options' => ['port' => $this->authorization['database_port']]]);
            unset($this->authorization['database_port']);
        }

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
     * Generates a response from the given request object.
     *
     * @param HttpFoundation\Request $request
     * @param int                    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    public function renderAction(HttpFoundation\Request $request, $expiryMinutes = 1)
    {
        $render = $this->getRender($request, $this->renderArguments());
        $view = Handler\ViewHandler::render($request->get('_route'), $render);

        return static::processRender($view, $expiryMinutes);
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
    public function pageRenderAction(HttpFoundation\Request $request, $page = null, $expiry_minutes = 1)
    {
        $render = $this->getSplitPageRender($request, $page, $this->renderArguments());
        $view = Handler\ViewHandler::render($request->get('_route'), $render);

        return static::processRender($view, $expiry_minutes);
    }
}
