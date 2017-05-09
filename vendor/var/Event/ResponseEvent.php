<?php

namespace Event;

use Handler;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation;

/**
 * Class ResponseEvent
 * Each time the framework handles a Request, a ResponseEvent event is now dispatched.
 */
class ResponseEvent extends EventDispatcher\Event
{
    /** @var HttpFoundation\Request */
    private $request;

    /** @var HttpFoundation\Response */
    private $response;

    /**
     * ResponseEvent constructor.
     *
     * @param HttpFoundation\Response $response
     * @param HttpFoundation\Request  $request
     */
    public function __construct(HttpFoundation\Response $response, HttpFoundation\Request $request)
    {
        $this->response = $response;
        $this->request = $request;
        $this->handleSession();
    }

    /**
     * @return HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    private function handleSession()
    {
        $sessionHandler = new Handler\Session\SessionHandler();
        if ($sessionHandler->startSession()) {
            $hasError = $this->response->headers->has('ErrorData') && $this->response->isRedirect() || $this->response->getContent() === '';
            $session = $sessionHandler->getSession($hasError, $this->response->headers->get('ErrorData') ?: null);
            $this->request->setSession($session);
        }
    }
}
