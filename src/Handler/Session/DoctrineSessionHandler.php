<?php

namespace App\Handler\Session;

use Doctrine\DBAL;

/**
 * Class DoctrineSessionHandler
 *
 * @see https://gist.github.com/xocasdashdash/48c3871aee9e898d4fb4
 */
class DoctrineSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var DBAL\Connection Conexión de Doctrine a la base de datos
     */
    private $connection;

    /**
     * @var string Entidad que uso para guardar los datos en la BD
     */
    private $entity;

    /**
     * @var string Column for session id
     */
    private $idCol;

    /**
     * @var string Column for session data
     */
    private $dataCol;

    /**
     * @var string Column for timestamp
     */
    private $timeCol;

    /**
     * DoctrineSessionHandler constructor.
     *
     * @param array $args
     *
     * @throws \InvalidArgumentException When "entity" option is not provided
     */
    public function __construct(array $args)
    {
        if (!isset($args['entity'])) {
            throw new \InvalidArgumentException('You must provide the "entity" option for a DoctrineSessionStorage.');
        }
        $this->connection = DBAL\DriverManager::getConnection(\defDb::dbDist());
        $this->entity = $args['entity'];
        $this->idCol = $args['id'];
        $this->dataCol = $args['data'];
        $this->timeCol = $args['time'];
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        // delete the record associated with this id
        $sql = "DELETE FROM $this->entity WHERE $this->idCol = :id";

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':id', $sessionId, 'string');
            $stmt->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Error while trying to delete a session: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        // delete the session records that have expired
        $sql = "DELETE FROM $this->entity WHERE $this->timeCol < :time";

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':time', time() - $maxlifetime, 'integer');
            $stmt->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Error while trying to delete expired sessions: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $sql = "SELECT $this->dataCol FROM $this->entity WHERE $this->idCol = :id";
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $stmt->execute();
            $sessionRows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if ($sessionRows) {
                return base64_decode($sessionRows[0][0]);
            }

            return '';
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
                $table = new DBAL\Schema\Table($this->entity);
                $table->addColumn('id', 'bigint', ['unsigned' => true, 'autoincrement' => true]);
                $table->addColumn($this->idCol, 'string', ['length' => 255]);
                $table->addColumn($this->dataCol, 'string', ['length' => 255]);
                $table->addColumn($this->timeCol, 'string', ['length' => 255]);
                $table->setPrimaryKey(['id']);
                $this->getConnection()->getSchemaManager()->createTable($table);

                return $this->read($sessionId);
            }
            throw new \RuntimeException(sprintf('Error while trying to read the session data: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        // Session data can contain non binary safe characters so we need to encode it.
        $encoded = base64_encode($data);

        // We use a MERGE SQL query when supported by the database.
        // Otherwise we have to use a transactional DELETE followed by INSERT to prevent duplicate entries under high concurrency.
        try {
            $mergeSql = $this->getMergeSql();
            if (null !== $mergeSql) {
                return $this->mergeAndCommit($mergeSql, $sessionId, $encoded);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Error while trying to write the session data: %s', $e->getMessage()), 0, $e);
        }
        try {
            $this->getConnection()->beginTransaction();
            $deleteStmt = $this->getConnection()->prepare(
                "DELETE FROM $this->entity WHERE $this->idCol = :id"
            );
            $deleteStmt->bindParam(':id', $sessionId, 'string');
            $deleteStmt->execute();

            $insertSql = "INSERT INTO $this->entity ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time)";

            return $this->mergeAndCommit($insertSql, $sessionId, $encoded);
        } catch (\Exception $e) {
            $this->getConnection()->rollback();
            throw new \RuntimeException(sprintf('Error while trying to write the session data: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Returns a merge/upsert (i.e. insert or update) SQL query when supported by the database.
     *
     * @return string|null The SQL string or null when not supported
     */
    private function getMergeSql()
    {
        switch ($this->getConnection()->getDriver()->getName()) {
            case 'pdo_mysql':
                return "INSERT INTO $this->entity ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) ".
                    "ON DUPLICATE KEY UPDATE $this->dataCol = VALUES($this->dataCol), $this->timeCol = VALUES($this->timeCol)";
            case 'oci8':
                // DUAL is Oracle specific dummy table
                return "MERGE INTO $this->entity USING DUAL ON ($this->idCol = :id) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = :data";
            case 'sqlsrv':
                // MS SQL Server requires MERGE be terminated by semicolon
                return "MERGE INTO $this->entity USING (SELECT 'x' AS dummy) AS src ON ($this->idCol = :id) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = :data;";
            case 'pdo_sqlite':
                return "INSERT OR REPLACE INTO $this->entity ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time)";
        }
    }

    /**
     * Return a DBAL\Connection instance
     *
     * @return DBAL\Connection
     */
    protected function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param string $sql
     * @param string $sessionId
     * @param string $encoded
     *
     * @return bool
     */
    private function mergeAndCommit($sql, $sessionId, $encoded)
    {
        $mergeStmt = $this->getConnection()->prepare($sql);
        $mergeStmt->bindParam(':id', $sessionId, 'string');
        $mergeStmt->bindParam(':data', $encoded, 'string');
        $mergeStmt->bindValue(':time', time(), 'integer');
        $mergeStmt->execute();

        $this->getConnection()->commit();

        return true;
    }
}
