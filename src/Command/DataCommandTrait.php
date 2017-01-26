<?php

namespace App\Command;

use Doctrine\DBAL;
use Symfony\Component\Console;

/**
 * Trait DataCommandTrait.
 */
trait DataCommandTrait
{
    /** @var \Doctrine\DBAL\Connection */
    protected $sc;

    /** @var \Doctrine\DBAL\Connection */
    protected $tc;

    /**
     * @param Console\Input\InputInterface $input
     * @param string                       $origin
     *
     * @return array
     *
     * @throws \Exception
     */
    private function fixPath(Console\Input\InputInterface $input, $origin)
    {
        $method = 'db'.ucfirst($input->getOption($origin));
        if (!method_exists(new \defDb(), $method)) {
            throw new \Exception(sprintf('Error, there is no `%s` method on the `%s` main class.', $method, '\defDb'));
        }
        $data = \defDb::$method();
        if (isset($data['path'])) {
            $path = str_replace('%kernel.root_dir%', ROOT_DIR.'/app', $data['path']);

            return array_merge($data, ['path' => $path]);
        }

        return $data;
    }

    /**
     * @param Console\Input\InputInterface $input
     * @param string                       $source
     * @param string                       $target
     */
    private function init(Console\Input\InputInterface $input, $source = 'source', $target = 'target')
    {
        ini_set('memory_limit', '-1');
        $this->batch = 100000;
        $this->config[$source] = $this->fixPath($input, $source);
        $this->config[$target] = $this->fixPath($input, $target);
        $this->config['keep-constraints'] = true;
        $this->config['tables'] = [];

        $tableNames = array_keys(array_merge(\def::dbCodes(), [\defDb::userEntity() => null]));
        foreach ($tableNames as $tableName) {
            echo sprintf('`%s` discovered', $tableName).PHP_EOL;
            $this->config['tables'][] = ['name' => $tableName, 'mode' => static::MODE_COPY];
        }
        /* @var \Doctrine\DBAL\Connection $sc */
        $this->sc = DBAL\DriverManager::getConnection($this->getConfig('source'));
        /* @var \Doctrine\DBAL\Connection $tc */
        $this->tc = DBAL\DriverManager::getConnection($this->getConfig('target'));
    }

    /**
     * @throws \Exception
     */
    private function prepare()
    {
        // make sure all connections are UTF8 in source
        try {
            if ($this->sc->getDatabasePlatform()->getName() === 'mysql') {
                $this->sc->executeQuery('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;');
            }
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'No connection')) {
                throw new \Exception('Source database connection is down.');
            } elseif (strpos($e->getMessage(), 'Unknown database')) {
                throw new \Exception(sprintf('Unknown source database: `%s`', $this->sc->getDatabase()));
            }
            throw new \Exception($e->getMessage());
        }
        // make sure all connections are UTF8 in target
        $dbTarget = $this->tc->getDatabase();
        try {
            if ($this->tc->getDatabasePlatform()->getName() === 'mysql') {
                $this->tc->executeQuery('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;');
                $dbs = $this->tc->fetchArray("SHOW DATABASES LIKE '$dbTarget';");
                if (in_array($dbTarget, $dbs)) {
                    throw new \Exception(sprintf('Target database `%s` already exists.', $dbTarget));
                }
            } elseif ($this->tc->getDatabasePlatform()->getName() === 'sqlite' && $file = realpath($this->getConfig('target.path'))) {
                $this->output->writeln(PHP_EOL.sprintf('Database `%s` already exists.', $dbTarget));
                $path = dirname($file).DIRECTORY_SEPARATOR;
                $oldFilename = str_replace($path, '', $file);
                $newFilename = basename($oldFilename, '.db3').'#'.(new \DateTime())->format('Y-m-d#H.i.s').'.db3';
                $this->output->writeln(PHP_EOL.sprintf('Backing up previous database: `%s` -> `%s`', $oldFilename, $newFilename));
                if (!rename($file, $path.$newFilename)) {
                    throw new \Exception(error_get_last()['message']);
                }
                $this->output->writeln(PHP_EOL.sprintf('Creating database: `%s`...', $oldFilename).PHP_EOL);
            }
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'No connection')) {
                throw new \Exception('Target database connection is down.');
            } elseif (strpos($e->getMessage(), 'Unknown database')) {
                $this->output->writeln(PHP_EOL.sprintf('Unknown target database: `%s`', $dbTarget));
                $this->output->writeln(PHP_EOL.sprintf('Creating target database `%s`...', $dbTarget).PHP_EOL);
                // @see https://github.com/doctrine/DoctrineBundle/blob/v1.5.2/Command/CreateDatabaseDoctrineCommand.php
                $tmp = $this->getConfig('target');
                // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
                unset($tmp['dbname'], $tmp['path'], $tmp['url']);
                $tmpConnection = DBAL\DriverManager::getConnection($tmp);
                $tmpConnection->getSchemaManager()->createDatabase($dbTarget);
            } else {
                throw new \Exception($e->getMessage());
            }
        }
    }

    /**
     * @param string $table
     * @param bool   $keyColumn
     */
    protected function copyTable($table, $keyColumn = false)
    {
        $columns = implode(array_map(function ($column) {
            return $this->sc->quoteIdentifier($column->getName());
        }, $table->getColumns()), ',');

        $sqlParameters = [];
        $maxKey = null;

        // set selection range
        if ($keyColumn) {
            $sqlMax = 'SELECT MAX('.$this->tc->quoteIdentifier($keyColumn).') FROM '.$this->tc->quoteIdentifier($table->getName());
            $maxKey = $this->tc->fetchColumn($sqlMax);
            if (isset($maxKey)) {
                $sqlParameters[] = $maxKey;
            }
        } else {
            // clear target table
            $this->truncateTable($this->tc, $table);
        }
        $this->output->write(PHP_EOL.sprintf('%s: copying ', $table->getName()));

        // count selection range
        $sqlCount = 'SELECT COUNT(1) FROM ('.$this->sc->quoteIdentifier($table->getName()).');';
        if ($keyColumn && isset($maxKey)) {
            $sqlCount .= ' WHERE '.$this->sc->quoteIdentifier($keyColumn).' > ?';
        }
        $totalRows = $this->sc->fetchColumn($sqlCount, $sqlParameters);
        $this->output->write(sprintf('%s rows (%s)', $totalRows, $keyColumn ? 'partial copy' : 'overwrite').PHP_EOL);

        $progress = new Console\Helper\ProgressBar($this->output, $totalRows);
        $progress->setFormatDefinition('debug', ' [%bar%] %percent:3s%% %elapsed:8s%/%estimated:-8s% %current% rows');
        $progress->setFormat('debug');

        $freq = (int) $totalRows / 20;
        $progress->setRedrawFrequency(($freq > 10) ? $freq : 10);
        $progress->start();

        // transfer sql
        $loopOffsetIndex = 0;
        do {
            // get source data
            $sql = 'SELECT '.$columns.' FROM '.$this->sc->quoteIdentifier($table->getName());
            // limit selection for PRIMARY KEY mode
            if ($keyColumn) {
                if (isset($maxKey)) {
                    $sql .= ' WHERE '.$this->sc->quoteIdentifier($keyColumn).' > ?';
                    $sqlParameters = [$maxKey];
                }
                $sql .= ' ORDER BY '.$this->sc->quoteIdentifier($keyColumn).' LIMIT '.$this->batch;
            } else {
                $sql .= ' LIMIT '.$this->batch.' OFFSET '.($this->batch * $loopOffsetIndex++);
            }
            if (count($rows = $this->sc->fetchAll($sql, $sqlParameters)) === 0) {
                // avoid div by zero in progress->advance
                break;
            }
            $stmt = $this->tc->prepare(
                'INSERT INTO '.$this->tc->quoteIdentifier($table->getName()).' ('.$columns.') '.
                'VALUES ('.implode(array_fill(0, count($table->getColumns()), '?'), ',').')'
            );
            $this->tc->beginTransaction();
            foreach ($rows as $row) {
                // remember max key
                if ($keyColumn) {
                    $maxKey = $row[$keyColumn];
                }
                $stmt->execute(array_values($row));
                $progress->advance(1);
            }
            $this->tc->commit();
        } while ($keyColumn && count($rows));

        $progress->finish();
        echo PHP_EOL;
    }
}
