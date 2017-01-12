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
    private $session;

    public function __construct(HttpFoundation\Response $response, HttpFoundation\Request $request)
    {
        $this->response = $response;
        $this->request = $request;
        $this->setSession();
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getRequest()
    {
        return $this->request;
    }

    private function setSession($options = [], $expireTime = 10)
    {
        /** @var bool $wasError */
        $wasError = false;
        if ($this->session instanceof HttpFoundation\Session\SessionInterface) {
            $wasError = $this->session->has('ErrorCode') && $this->session->has('ErrorMessage');
        } else {
            if (DEBUG)
                $this->startSQLiteSession($options, $expireTime);
            else
                $this->startMemcacheSession($options, $expireTime);
        }
        /** @var bool $isError */
        $isError = $this->response->isRedirect() || $this->response->getContent() === '';
        if ($isError && $this->response->headers->has('ErrorCode') && $this->response->headers->has('ErrorMessage')) {
            $this->session->set('ErrorCode', $this->response->headers->get('ErrorCode'));
            $this->session->set('ErrorMessage', $this->response->headers->get('ErrorMessage'));
        } elseif (time() - $this->session->getMetadataBag()->getLastUsed() > $expireTime || $wasError) {
            $this->session->invalidate();
            //throw new SessionExpired; // redirect to expired session page
        }
        $this->request->setSession($this->session);
    }

    private function startMemcacheSession($options = [], $expireTime = 10, $lock = true, $lockWait = 250, $maxWait = 2)
    {
        $memcache = new \MemcachePool();
        if ($memcache->connect('localhost', 11211) !== false) {
            $this->session = $this->request->getSession();
            $handler = new Handler\Session\LockingSessionHandler($memcache, [
                'expiretime' => $expireTime,
                'locking' => $lock,
                'spin_lock_wait' => $lockWait,
                'lock_max_wait' => $maxWait, ]);
            $storage = new HttpFoundation\Session\Storage\NativeSessionStorage($options, $handler);
            $this->session = new HttpFoundation\Session\Session($storage);
            $this->session->start();
        } else {
            throw new \Exception('Cannot connect to Session default server');
        }
    }

    private function startSQLiteSession($options = [], $expireTime = 10, $lock = true, $lockWait = 250, $maxWait = 2)
    {
        $storage = new HttpFoundation\Session\Storage\NativeSessionStorage($options, new Handler\Session\SQLiteSessionHandler(ROOT_DIR.'/app/Resources/db/sessions.db'));

    }
}
