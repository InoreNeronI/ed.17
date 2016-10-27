<?php

namespace App;

use medoo;
use Symfony\Component\Yaml;

const CONFIG_FILES_DIR  = '/app/config';
const USER_TABLE        = 'edg020_ikasleak';
const MAP_SLUG          = 'login';

/**
 * Class Repository
 * @package App
 */
class Repository extends medoo
{
	/** @var array $parameters */
	private static $parameters;

	/** @var string $configFilesDir */
	private static $configFilesDir = CONFIG_FILES_DIR;

	/** @var string $homeSlug */
	private static $mapSlug = MAP_SLUG;

	/** @var string $userTable */
	private static $userTable = USER_TABLE;

	/**
	 * Repository constructor.
	 *
	 * @param string|null $dir
	 * @param string|null $mapSlug
	 * @param string|null $userTable
	 */
	public function __construct($dir = null, $mapSlug = null, $userTable = null)
	{
		static::$parameters = static::parseConfig();
		empty($dir) ?: static::$configFilesDir = $dir;
		empty($mapSlug) ?: static::$mapSlug = $mapSlug;
		empty($userTable) ?: static::$userTable = $userTable;
		return parent::__construct( array(
			'database_type' => static::$parameters['database_type'],
			'database_name' => static::$parameters['database_name'],
			'server' => static::$parameters['database_host'],
			'port' => static::$parameters['database_port'],
			'username' => static::$parameters['database_user'],
			'password' => static::$parameters['database_password'],
			'charset' => static::$parameters['database_charset']
		) );
	}

	/**
	 * @return array
	 */
	public function getParameters()
	{
		return static::$parameters;
	}

	/**
	 * @param string $filename
	 *
	 * @return array
	 */
	private static function parseConfig($filename = 'parameters')
	{
		$config = Yaml\Yaml::parse(file_get_contents(dirname(__DIR__).static::$configFilesDir."/$filename.yml"));
		return $filename === 'parameters' ? $config[$filename] : $config;
	}

	/**
	 * @param array $tables
	 * @param string|null $prefix
	 * @param string|null $break_table
	 *
	 * @return array
	 */
	private static function mapFields($tables, $prefix = null, $break_table = null)
	{
		$db_fields = array();
		foreach ($tables as $table_name => $table_field) {
			$name = $prefix . $table_name;
			if (is_array($table_field))
				$db_fields = array_merge($db_fields, static::mapFields($table_field, $name, $break_table));
			else
				$db_fields[$name] = $table_field;
			if ($table_name === $break_table)
				return $db_fields;
		}
		return $db_fields;
	}

	/**
	 * @param array $fields
	 * @param string|null $prefix
	 * @param string|null $break_table
	 *
	 * @return array
	 */
	private static function parseFields($fields, $prefix = null, $break_table = null)
	{
		$db_fields = array();
		$config = static::parseConfig('map/'.static::$mapSlug);
		foreach ($fields as $form_field_name => $form_field_value) {
			if (array_key_exists($form_field_name, $config))
				$db_fields[$form_field_name] = static::mapFields($config[$form_field_name], $prefix, $break_table);
		}
		return $db_fields;
	}

	/**
	 * @param array $fields
	 *
	 * @return array
	 */
	public static function checkCredentials($fields)
	{
		$fields = static::parseFields($fields, null, static::$userTable);
		print_r($fields);
	}
}
