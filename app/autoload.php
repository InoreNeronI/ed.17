<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
class NoticeException extends Exception
{
    public function __toString()
    {
        return sprintf("Notice%s: {$this->message}", $this->code !== 0 ? " #{$this->code}" : '');
    }
}
class WarningException extends Exception
{
    public function __toString()
    {
        return sprintf("Warning%s: {$this->message}", $this->code !== 0 ? " #{$this->code}" : '');
    }
}
/* @see: http://stackoverflow.com/a/4410769
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error['type'] === E_ERROR) {
        // fatal error has occured
        //throw new Exception($error['message'], 404);
    }
});*/
set_error_handler(function ($id, $msg) {
    throw $id === E_WARNING ? new WarningException($msg, $id) : $id === E_NOTICE ? new NoticeException($msg, $id) : null;
}, E_ALL);

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
