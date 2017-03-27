<?php

namespace Security\Connection;

use Doctrine\DBAL;

/**
 * @see https://github.com/air-php/database/blob/master/src/Connection.php
 *
 * Class Connection
 */
class Connection implements ConnectionInterface
{
    /**
     * @var array An array of connection parameters
     */
    private $connectionParams;

    /**
     * @var DBAL\Connection A database connection
     */
    private $connection;

    /**
     * @var bool
     */
    protected static $debug = DEBUG;

    /**
     * Constructor to collect required database credentials.
     *
     * @param string $host     The hostname
     * @param string $username The database username
     * @param string $password The database password
     * @param string $database The name of the database
     * @param string $driver   The database driver, defaults to pdo_mysql
     * @param array  $options  The driver options passed to the pdo connection
     */
    public function __construct($host, $username, $password, $database, $driver = 'pdo_mysql', array $options = [])
    {
        $this->connectionParams = [
            'dbname' => $database,
            'user' => $username,
            'password' => $password,
            'host' => $host,
            'driver' => $driver,
            'driverOptions' => $options,
        ];
    }

    /**
     * Returns a Doctrine query builder object.
     *
     * @return DBAL\Query\QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->getConnection()->createQueryBuilder();
    }

    /**
     * Returns the connection object.
     *
     * @return DBAL\Connection
     */
    public function getConnection()
    {
        if (!isset($this->connection)) {
            $this->connection = DBAL\DriverManager::getConnection($this->connectionParams, new DBAL\Configuration());
        }

        return $this->connection;
    }

    /**
     * Sets a timezone.
     *
     * @param string $timezone The timezone you wish to set
     */
    public function setTimezone($timezone)
    {
        $smt = $this->getConnection()->prepare('SET time_zone = ?');
        $smt->bindValue(1, $timezone);
        $smt->execute();
    }

    /**
     * How long we should wait before restart locked transaction in seconds.
     *
     * @var int
     */
    protected $transactionRestartDelay = 1;

    /**
     * @see https://github.com/weavora/doctrine-extensions/blob/master/lib/Weavora/Doctrine/DBAL/Connection.php
     * Execute update query
     * Return number of affected rows
     *
     * @param string $query       SQL query
     * @param array  $params
     * @param int    $maxAttempts
     *
     * @throws \Exception
     *
     * @return int number of affected rows
     */
    public function locksSafeUpdate($query, $params = [], $maxAttempts = 3)
    {
        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                return $this->getConnection()->executeUpdate($query, $params);
            } catch (\Exception $e) {
                // we need try execute query again in case of followign MySQL errors:
                // Error: 1205 SQLSTATE: HY000 (ER_LOCK_WAIT_TIMEOUT) Message: Lock wait timeout exceeded; try restarting transaction
                // Error: 1213 SQLSTATE: 40001 (ER_LOCK_DEADLOCK) Message: Deadlock found when trying to get lock; try restarting transaction
                if (stripos($e->getMessage(), 'try restarting transaction') === false || $attempt === $maxAttempts) {
                    throw $e;
                }

                sleep($this->transactionRestartDelay);
            }
        }
    }
}
