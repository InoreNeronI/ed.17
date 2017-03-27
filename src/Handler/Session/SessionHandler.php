<?php

namespace Handler\Session;

use Handler;
use Symfony\Component\HttpFoundation;

/**
 * Class SessionHandler
 * Each time the framework handles a Request, a Session is created/managed.
 */
class SessionHandler
{
    /** @var bool */
    private $debug;

    /** @var int */
    private $expireTime;

    /** @var array */
    private $options;

    /** @var string */
    private $savePath;

    /** @var HttpFoundation\Session\Session */
    private $session;

    /**
     * SessionHandler constructor.
     *
     * @param string $savePath   path to session file itself
     * @param int    $expireTime
     * @param array  $options    Session configuration options:
     *                           cache_limiter, "" (use "0" to prevent headers from being sent entirely).
     *                           cookie_domain, ""
     *                           cookie_httponly, ""
     *                           cookie_lifetime, "0"
     *                           cookie_path, "/"
     *                           cookie_secure, ""
     *                           entropy_file, ""
     *                           entropy_length, "0"
     *                           gc_divisor, "100"
     *                           gc_maxlifetime, "1440"
     *                           gc_probability, "1"
     *                           hash_bits_per_character, "4"
     *                           hash_function, "0"
     *                           name, "PHPSESSID"
     *                           referer_check, ""
     *                           serialize_handler, "php"
     *                           use_cookies, "1"
     *                           use_only_cookies, "1"
     *                           use_trans_sid, "0"
     *                           upload_progress.enabled, "1"
     *                           upload_progress.cleanup, "1"
     *                           upload_progress.prefix, "upload_progress_"
     *                           upload_progress.name, "PHP_SESSION_UPLOAD_PROGRESS"
     *                           upload_progress.freq, "1%"
     *                           upload_progress.min-freq, "1"
     *                           url_rewriter.tags, "a=href,area=href,frame=src,form=,fieldset="
     * @param bool   $debug
     */
    public function __construct($savePath = null, $expireTime = 10, array $options = [], $debug = DEBUG)
    {
        $this->expireTime = $expireTime;
        // Get session save-path.
        if (is_null($savePath)) {
            $savePath = ini_get('session.save_path');
        } else {
            $savePath = ROOT_DIR.$savePath;
        }
        if (!is_writable($savePath) && !mkdir($savePath, 0775, true)) {
            throw new \RuntimeException('Couldn\'t save to Sessions\' default path because write access isn\'t granted');
        }
        $this->savePath = $savePath;
        $this->options = $options;
        $this->debug = $debug;
        $this->session = null;
    }

    /**
     * @return HttpFoundation\Session\Session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Starts session.
     *
     * @return bool
     */
    public function startSession()
    {
        return /*$this->debug ? $this->doctrineSession('ed_2017_session')->start() : */$this->filesystemSession()->start();
    }

    /**
     * Checks if any error was thrown.
     *
     * @return bool
     */
    public function hasError()
    {
        return $this->session instanceof HttpFoundation\Session\SessionInterface && $this->session->isStarted() && $this->session->has('ErrorData');
    }

    /**
     * @param string $handler
     */
    private function setSessionConfig($handler = 'files')
    {
        // Set any ini values.
        ini_set('session.save_handler', $handler);
        ini_set('session.save_path', $this->savePath);
        // Set session lifetime.
        /* @see http://stackoverflow.com/a/19597247 */
        ini_set('session.cookie_lifetime', $this->expireTime);
        ini_set('session.gc_maxlifetime', $this->expireTime / 2);
    }

    /**
     * Driver for the native filesystem session save handler.
     *
     * @return HttpFoundation\Session\Session
     */
    private function filesystemSession()
    {
        $this->setSessionConfig();
        $storage = new HttpFoundation\Session\Storage\NativeSessionStorage(
            array_merge($this->options, ['cache_limiter' => session_cache_limiter()]),
            new HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler());
        $this->session = new HttpFoundation\Session\Session($storage);

        return $this->session;
    }

    /**
     * Driver for the memcache session save handler provided by the Memcache PHP extension.
     *
     * @see memcache-monitor: https://github.com/andrefigueira/memcache-monitor
     * @see memcadmin: https://github.com/rewi/memcadmin
     *
     * @param string $host
     * @param int    $port
     * @param bool   $lock
     * @param int    $lockWait
     * @param int    $maxWait
     *
     * @return HttpFoundation\Session\Session
     *
     * @throws \Exception
     */
    private function memcacheSession($host = 'localhost', $port = 11211, $lock = true, $lockWait = 250, $maxWait = 2)
    {
        if (!extension_loaded('memcache')) {
            throw new \RuntimeException('PHP does not have "memcache" extension enabled');
        }
        $this->setSessionConfig('memcache');
        $memcache = new \MemcachePool();
        $memcache->connect($host, $port);
        $storage = new HttpFoundation\Session\Storage\NativeSessionStorage($this->options, new Handler\Session\LockingSessionHandler($memcache, [
            'expiretime' => $this->expireTime,
            'locking' => $lock,
            'spin_lock_wait' => $lockWait,
            'lock_max_wait' => $maxWait, ]));
        $this->session = new HttpFoundation\Session\Session($storage);

        return $this->session;
    }

    /**
     * No more supported: https://bugs.php.net/bug.php?id=53713&edit=1
     * Driver for the sqlite session save handler provided by the SQLite PHP extension.
     *
     * @see https://github.com/zikula/NativeSession/blob/4992c11f7b832f05561b98b0c192ce852e6ed602/Drak/NativeSession/NativeSqliteSessionHandler.php
     *
     * @return HttpFoundation\Session\Session
     */
    private function sqliteSession()
    {
        if (!extension_loaded('sqlite')) {
            throw new \RuntimeException('PHP does not have "sqlite" extension enabled');
        }
        $this->setSessionConfig('sqlite');

        // Set rest of session related ini values.
        foreach ($this->options as $key => $value) {
            if (strpos($key, 'sqlite')) {
                ini_set($key, $value);
            }
        }
        /** @var HttpFoundation\Session\Storage\NativeSessionStorage $storage */
        $storage = new HttpFoundation\Session\Storage\NativeSessionStorage($this->options, new HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler());
        $this->session = new HttpFoundation\Session\Session($storage);

        return $this->session;
    }

    /**
     * Storing symfony sessions using doctrine. Based on Symfony\Component\HttpFoundation\Session\Storage\Handler\PDOSessionHandler
     *
     * @see https://gist.github.com/xocasdashdash/48c3871aee9e898d4fb4
     *
     * @param string $entity The name of the table [required]
     * @param string $id     The column where to store the session id [default: session_id]
     * @param string $data   The column where to store the session data [default: session_value]
     * @param string $time   The column where to store the timestamp [default: session_time]
     *
     * @return HttpFoundation\Session\Session
     */
    private function doctrineSession($entity, $id = 'session_id', $data = 'session_value', $time = 'session_time')
    {
        /** @var Handler\Session\DoctrineSessionHandler $storage */
        $storage = new HttpFoundation\Session\Storage\NativeSessionStorage(
            $this->options,
            new Handler\Session\DoctrineSessionHandler([
            'entity' => $entity, 'id' => $id, 'data' => $data, 'time' => $time,
        ]));
        $this->session = new HttpFoundation\Session\Session($storage);

        return $this->session;
    }
}
