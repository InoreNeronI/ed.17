<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
class NoticeException extends Exception
{
    public function __toString()
    {
        return  "Notice #{$this->code}: {$this->message}";
    }
}
class WarningException extends Exception
{
    public function __toString()
    {
        return  "Warning #{$this->code}: {$this->message}";
    }
}
set_error_handler('error_handler', E_ALL);
function error_handler($errno, $errstr)
{
    if ($errno === E_WARNING) {
        throw new WarningException($errstr, $errno);
    } elseif ($errno === E_NOTICE) {
        throw new NoticeException($errstr, $errno);
    }
}
define('DEBUG', php_sapi_name() !== 'cli-server' &&
                !isset($_SERVER['HTTP_CLIENT_IP']) &&
                !isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
                in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', 'fe80::1', '::1', '10.212.11.240']) ? true : false);
define('TURBO', true);

// Require and return loader
$loader = realpath(ROOT_DIR.sprintf('/vendor%s/autoload.php', TURBO ? '-tiny' : ''));

if ($loader !== false) {
    try {
        /* @return \Composer\Autoload\ClassLoader */
        return require $loader;
    } catch (Exception $e) {
        /* @throw \Exception */
        throw new \Exception(sprintf('Internal error: %s', $e->getMessage()));
    }
} else {
    /* @throw \Exception */
    throw new \Exception('Vendor files not found, please run "Composer" dependency manager: https://getcomposer.org/');
}
