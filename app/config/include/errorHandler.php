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
/*// @see: http://stackoverflow.com/a/4410769
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error['type'] === E_ERROR) {
        // fatal error has occured
        //throw new Exception($error['message'], 404);
    }
});*/
error_reporting(E_ALL | E_STRICT);
set_error_handler(function ($id, $msg) {
    throw $id === E_WARNING ? new WarningException($msg, $id) : $id === E_NOTICE ? new NoticeException($msg, $id) : new \Exception($msg, $id);
}, E_ALL);
