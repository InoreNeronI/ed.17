<?php

namespace App\Event;

use App\Handler;
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

    /**
     * @param int    $expireTime
     * @param string $savePath
     */
    private function handleSession($expireTime = 20, $savePath = '/app/Resources/session')
    {
        $sessionHandler = new Handler\Session\SessionHandler($expireTime, $savePath);
        if ($sessionHandler->startSession()) {
            $session = $sessionHandler->getSession();
            $isError = $this->response->isRedirect() || $this->response->getContent() === '';
            if ($isError && $this->response->headers->has('ErrorCode') && $this->response->headers->has('ErrorMessage')) {
                $session->set('ErrorCode', $this->response->headers->get('ErrorCode'));
                $session->set('ErrorMessage', $this->response->headers->get('ErrorMessage'));
            } elseif (time() - $session->getMetadataBag()->getLastUsed() > $expireTime || $sessionHandler->hasError()) {
                $session->invalidate();   //throw new SessionExpired; // redirect to expired session page
            }
            $this->request->setSession($session);
        }
    }
}
