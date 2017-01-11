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
    private $request;
    private $response;

    public function __construct(HttpFoundation\Response $response, HttpFoundation\Request $request)
    {
        $this->response = $response;
        $this->request = $request;
        $this->handleSession();
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getRequest()
    {
        return $this->request;
    }

    private function handleSession($options = [], $expiretime = 10)
    {
        //$storage = new HttpFoundation\Session\Storage\NativeSessionStorage($options, new Handler\NativeSqliteSessionHandler(ROOT_DIR.'/app/Resources/db/sessions.db'));
        $memcache = new \MemcachePool();
        if ($memcache->connect('localhost', 11211) !== false) {
            if (!($session = $this->request->getSession() instanceof HttpFoundation\Session\SessionInterface)) {
                $handler = new Handler\Session\LockingSessionHandler($memcache, [
                    'expiretime' => $expiretime,
                    'locking' => true,
                    'spin_lock_wait' => 250,
                    'lock_max_wait' => 2, ]);
                $storage = new HttpFoundation\Session\Storage\NativeSessionStorage($options, $handler);
                $session = new HttpFoundation\Session\Session($storage);
                $session->start();
            }
            $isError = $this->response->isRedirect() || $this->response->getContent() === '';
            $wasError = $session->has('warn-code') && $session->has('warn-text');
            if ($isError && $this->response->headers->has('warn-code') && $this->response->headers->has('warn-text')) {
                $session->set('warn-code', $this->response->headers->get('warn-code'));
                $session->set('warn-text', $this->response->headers->get('warn-text'));
                //print_r($this->response->headers->all());
                //print_r([$code,$text]);exit;
            } elseif (time() - $session->getMetadataBag()->getLastUsed() > $expiretime || $wasError) {
                $session->invalidate();
                //throw new SessionExpired; // redirect to expired session page
            }
            $this->request->setSession($session);
        } else {
            throw new \Exception('Cannot connect to Memcache default server');
        }
    }
}
