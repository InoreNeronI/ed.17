<?php

namespace App;

use medoo;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Repository
 * @package App
 */
class Repository extends medoo
{
	/** @var array $parameters */
	private $parameters;

	/**
	 * Repository constructor.
	 */
	public function __construct()
	{
		$this->parameters = static::parseConfig();
		return parent::__construct( array(
			'database_type' => $this->parameters['database_type'],
			'database_name' => $this->parameters['database_name'],
			'server' => $this->parameters['database_host'],
			'port' => $this->parameters['database_port'],
			'username' => $this->parameters['database_user'],
			'password' => $this->parameters['database_password'],
			'charset' => $this->parameters['database_charset']
		) );
	}

	/**
	 * @return array
	 */
	public function getParameters()
	{
		return $this->parameters;
	}

	/**
	 * @param string $slug
	 *
	 * @return array
	 */
	private static function parseConfig($slug = 'parameters')
	{
		$config = Yaml::parse(file_get_contents(__DIR__."/../app/config/$slug.yml"));
		return $slug === 'parameters' ? $config[$slug] : $config;
	}

	/**
	 * @param string $slug
	 *
	 * @return array
	 */
	private static function parseDBFields($slug = 'login')
	{
		return static::parseConfig("field/$slug");
	}

	/**
	 * @param $tables
	 * @param string $prefix
	 * @param string $break_table
	 *
	 * @return array
	 */
	private static function mergeDBFields($tables, $prefix = '', $break_table = 'edg020_ikasleak')
	{
		$db_fields = array();
		foreach ($tables as $table_name => $table_field) {
			//$length = strlen($table_name);
			$name = $prefix . $table_name;
			if (/*strrpos($table_name, '_') === --$length*/is_array($table_field))
				$db_fields = array_merge( $db_fields, static::mergeDBFields($table_field, $name, $break_table) );
			else
				$db_fields[$name] = $table_field;
			if ($table_name === $break_table)
				return $db_fields;
		}
		return $db_fields;
	}

	/**
	 * @param $form_fields
	 * @param string $slug
	 * @param string $table
	 *
	 * @return array
	 */
	public static function checkCredentials($form_fields, $slug = 'login', $table = 'edg020_ikasleak')
	{
		$db_fields = array();
		$config = static::parseDBFields($slug);
		foreach ($form_fields as $form_field_name => $form_field_value) {
			if (array_key_exists($form_field_name, $config))
				$db_fields[$form_field_name] = static::mergeDBFields($config[$form_field_name], $table);
		}
		print_r($db_fields);
	}
}
