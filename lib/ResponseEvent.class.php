<?php
namespace App;

use Symfony\Component\HttpFoundation;
use Symfony\Component\EventDispatcher;

/**
 * Class ResponseEvent
 * Each time the framework handles a Request, a ResponseEvent event is now dispatched.
 *
 * @package App
 */
class ResponseEvent extends EventDispatcher\Event
{
    private $request;
    private $response;

    public function __construct(HttpFoundation\Response $response, HttpFoundation\Request $request)
    {
        $this->response = $response;
        $this->request = $request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getRequest()
    {
        return $this->request;
    }
}