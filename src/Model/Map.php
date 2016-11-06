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
     * @param array  $messages
     * @param string $langISOCode
     * @param string $tableName
     *
     * @return array
     */
    public static function localizeMessages(array $messages, $langISOCode = null, $tableName = null)
    {
        foreach ($messages as $key => $message) {
            if (is_array($message)) {
                static::$localizedMsg = [];
                foreach ($message as $msg_key => $msg_value) {
                    if (($msg = static::localizeMessage($msg_key, $msg_value, $langISOCode, $tableName)) !== false) {
                        $messages[$key] = $msg;
                    }
                }
                empty(static::$localizedMsg) ?: $messages[$key] = implode(' / ', static::$localizedMsg);
            } elseif (($msg = static::localizeMessage($key, $message, $langISOCode, $tableName)) !== false) {
                $messages = $msg;
            }
        }

        return $messages;
    }

    /**
     * @param string $msg_key
     * @param string $msg_value
     * @param string $langISOCode
     * @param string $tableName
     *
     * @return array|false
     */
    public static function localizeMessage($msg_key, $msg_value, $langISOCode = null, $tableName = null)
    {
        if ($langISOCode === $msg_key/* && !empty($msg_value)*/) {
            return $msg_value;
        } elseif (is_null($langISOCode) && in_array($msg_key, \def::langISOCodes()) && !empty($msg_value)) {
            static::$localizedMsg[] = $msg_value;
        } elseif (strpos($tableName, $msg_key) && in_array($msg_key, \def::periods())) {
            return static::localizeMessages($msg_value, $langISOCode, $tableName);
        }

        return false;
    }
}
