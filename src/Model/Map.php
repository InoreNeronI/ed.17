<?php

namespace App\Model;

use App\Model\DBAL\Connection;

/**
 * Class Model.
 */
class Map extends Connection
{
    /** @var array */
    protected static $localizedMsg;

    /**
     * Model constructor.
     *
     * @param string|null $host
     * @param string|null $username
     * @param string|null $password
     * @param string|null $database
     * @param string|null $driver
     * @param array       $options
     */
    public function __construct($host = null, $username = null, $password = null, $database = null, $driver = 'pdo_mysql', array $options = [])
    {
        $params = empty($options) ? \def::parameters() : $options;
        $host = empty($host) ? $params['database_host'] : $host;
        $username = empty($username) ? $params['database_user'] : $username;
        $password = empty($password) ? $params['database_password'] : $password;
        $database = empty($database) ? $params['database_name'] : $database;
        $driver = empty($driver) ? $params['database_driver'] : $driver;
        parent::__construct($host, $username, $password, $database, $driver, ['port' => $params['database_port']]);
    }

    /**
     * @param array       $fields
     * @param string      $map
     * @param string|null $break_table
     * @param string|null $prefix
     *
     * @return array
     */
    protected static function parseFields(array $fields, $map = 'index', $break_table = null, $prefix = null)
    {
        $db_fields = [];
        $config = parseConfig($map);
        foreach ($fields as $form_field_name => $form_field_value) {
            if (array_key_exists($form_field_name, $config)) {
                $db_fields[$form_field_name] = static::mapFields($config[$form_field_name], $break_table, $prefix);
            }
        }

        return $db_fields;
    }

    /**
     * @param array       $tables
     * @param string|null $break_table
     * @param string|null $prefix
     *
     * @return array
     */
    protected static function mapFields(array $tables, $break_table = null, $prefix = null)
    {
        $db_fields = [];
        foreach ($tables as $table_name => $table_field) {
            $name = $prefix . $table_name;
            if (is_array($table_field)) {
                $db_fields = array_merge($db_fields, static::mapFields($table_field, $break_table, $name));
            } else {
                $db_fields[$name] = $table_field;
            }
            if ($table_name === $break_table) {
                return $db_fields;
            }
        }

        return $db_fields;
    }

    /**
     * @param array       $messages
     * @param string|null $lang
     * @param string|null $period
     *
     * @return array|false
     */
    public static function localizeMessages(array $messages, $lang = null, $period = null)
    {
        $lang = is_null($lang) && isset($messages['lang']) ? $messages['lang'] : $lang;
        $period = is_null($period) && isset($messages['period']) ? $messages['period'] : $period;
        foreach ($messages as $key => $message) {
            if ($key === 'actions'/* && !is_null($period)*/) {
                foreach ($message as $k => $v) {
                    if (strpos($k, $period) === false) {
                        unset($messages[$key][$k]);
                    }
                }
                $messages[$key] = static::localizeMessages($messages[$key], $lang, $period);
            } elseif (is_array($message)) {
                static::$localizedMsg = [];
                foreach ($message as $msg_key => $msg_value) {
                    if (($msg = static::localizeMessage($msg_key, $msg_value, $lang, $period)) !== false) {
                        $messages[$key] = $msg;
                    }
                }
                empty(static::$localizedMsg) ?: $messages[$key] = implode(' / ', static::$localizedMsg);
            } elseif (($msg = static::localizeMessage($key, $message, $lang, $period)) !== false) {
                $messages = $msg;
            }
        }

        return $messages;
    }

    /**
     * @param string      $msg_key
     * @param string      $msg_value
     * @param string|null $lang
     * @param string|null $period
     *
     * @return array|false
     */
    public static function localizeMessage($msg_key, $msg_value, $lang = null, $period = null)
    {
        if ($msg_key === $lang/* && !empty($msg_value)*/) {
            return $msg_value;
        } elseif ($msg_key === $period) {
            return static::localizeMessages($msg_value, $lang);
        } elseif (is_null($lang) && in_array($msg_key, \def::langISOCodes()) && !empty($msg_value)) {
            static::$localizedMsg[] = $msg_value;
        }

        return false;
    }
}
