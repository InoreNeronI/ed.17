<?php

namespace App;

use App\Model\DBAL\Connection;

/**
 * Class Model.
 */
class Model extends Connection
{
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
	    $params = empty($params) ? \def::parameters() : $params;
        $host = empty($host) ? $params['database_host'] : $host;
        $username = empty($username) ? $params['database_user'] : $username;
        $password = empty($password) ? $params['database_password'] : $password;
        $database = empty($database) ? $params['database_name'] : $database;
        $driver = empty($driver) ? $params['database_driver'] : $driver;
        parent::__construct($host, $username, $password, $database, $driver, [
            'port' => $params['database_port'],
        ]);
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
            $name = $prefix.$table_name;
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
}
