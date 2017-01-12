<?php

/** @see https://github.com/zikula/NativeSession/blob/master/Drak/NativeSession/NativeSqliteSessionHandler.php */

namespace App\Handler\Session;

use Symfony\Component\HttpFoundation;

/**
 * NativeSqliteSessionHandler.
 *
 * Driver for the sqlite session save handler provided by the SQLite PHP extension.
 *
 * @author Drak <drak@zikula.org>
 */
class SQLiteSessionHandler extends HttpFoundation\Session\Storage\Handler\NativeSessionHandler
{
    /**
     * Constructor.
     *
     * @param string $savePath path to SQLite database file itself
     * @param array  $options  Session configuration options:
     *                         cache_limiter, "" (use "0" to prevent headers from being sent entirely).
     *                         cookie_domain, ""
     *                         cookie_httponly, ""
     *                         cookie_lifetime, "0"
     *                         cookie_path, "/"
     *                         cookie_secure, ""
     *                         entropy_file, ""
     *                         entropy_length, "0"
     *                         gc_divisor, "100"
     *                         gc_maxlifetime, "1440"
     *                         gc_probability, "1"
     *                         hash_bits_per_character, "4"
     *                         hash_function, "0"
     *                         name, "PHPSESSID"
     *                         referer_check, ""
     *                         serialize_handler, "php"
     *                         use_cookies, "1"
     *                         use_only_cookies, "1"
     *                         use_trans_sid, "0"
     *                         upload_progress.enabled, "1"
     *                         upload_progress.cleanup, "1"
     *                         upload_progress.prefix, "upload_progress_"
     *                         upload_progress.name, "PHP_SESSION_UPLOAD_PROGRESS"
     *                         upload_progress.freq, "1%"
     *                         upload_progress.min-freq, "1"
     *                         url_rewriter.tags, "a=href,area=href,frame=src,form=,fieldset="
     */
    public function __construct($savePath = null, array $options = [])
    {
        if (!extension_loaded('sqlite3')) {
            throw new \RuntimeException('PHP does not have "sqlite" extension registered');
        }

        if (null === $savePath) {
            $savePath = ini_get('session.save_path');
        }

        phpinfo();
        ini_set('session.save_handler', 'sqlite3');
        ini_set('session.save_path', $savePath);
        //ini_set('[sqlite3].sqlite3.extension_dir', $savePath.'/ext');
        /* @see http://stackoverflow.com/a/19597247 */
        //ini_set('session.cookie_lifetime', 7200);
        //ini_set('session.gc_maxlifetime', 3600);

        $this->setOptions($options);
    }

    /**
     * Set any sqlite ini values.
     *
     * @see http://php.net/sqlite.configuration
     *
     * @param array $options
     */
    protected function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            if (in_array($key, ['sqlite.assoc_case'])) {
                ini_set($key, $value);
            }
        }
    }
}
