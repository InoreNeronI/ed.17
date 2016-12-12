<?php

namespace App\Model\Connection;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * @url https://github.com/air-php/database/blob/master/src/ConnectionInterface.php
 *
 * Interface ConnectionInterface
 */
interface ConnectionInterface
{
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
    public function __construct(
        $host,
        $username,
        $password,
        $database,
        $driver = 'pdo_mysql',
        array $options = []
    );

    /**
     * Returns a Doctrine query builder object.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder();

    /**
     * Sets a timezone.
     *
     * @param string $timezone The timezone you wish to set
     */
    public function setTimezone($timezone);
}
