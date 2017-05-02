<?php

namespace Command;

use Doctrine\DBAL;
use Symfony\Component\Console;

class DataExtractCommand extends Console\Command\Command
{
    private static $baseBuild = '0.5';

    private static $commonColumnOptions = ['length' => 40, 'notnull' => true];

    private static $ignoredExceptionMessages = ['Base table or view already exists'];

    private static $ignoredTables = ['edg051_testuak_dbh_simul', 'erantzunak_dbh_simul']; /*

    private static $ignoredTableSqlConditionFields = ['build', 'code', 'id', 'time'];
    private static $ignoredTableSqlConditionValues = [' <> null', ' <> null', ' <> "ed17-10030"', ' <> "2017-02-04 19:37:43.832881"'];*/

    private static $ignoredTablePrefixes = ['05_', '10_', '20_', '30_'];

    private static $statements = [];
    public static $totalInjected = 0;
    public static $totalIgnored = 0;
    public static $totalWeird = 0;
    public static $totalErrors = 0;

    private static $versioningTableNamePrefix = 'edg051_testuak_';

    private static $versioningTablePrefix = 'erantzunak_';

    private static $versioningTablePrimaryIndex = ['build', 'code', 'id'];

    protected function configure()
    {
        $this->setName('extract-db')
            ->setDescription('Extract encrypted zip and import `data.sql` and `data-structure.sql` to database')
            ->addOption('file', 'f', Console\Input\InputOption::VALUE_REQUIRED, 'Zip archive path')
            ->addOption('password', 'p', Console\Input\InputOption::VALUE_REQUIRED, 'Zip archive password')
            ->addOption('version', 'v', Console\Input\InputOption::VALUE_OPTIONAL, 'Zip archive version', static::$baseBuild);
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $zip = new \ZipArchive();
        $file = $input->getOption('file');
        $pw = $input->getOption('password');
        $v = $input->getOption('version');
        $zipStatus = $zip->open($file);
        $extractPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'extracted';
        if ($zipStatus === true) {
            if (!$zip->setPassword($pw) || !$zip->extractTo($extractPath)) {
                throw new \Exception(sprintf('Error, extraction of `%s` failed (wrong `%s` password?).', $file, $pw));
            }
            $zip->close();
            $structure = $extractPath.DIRECTORY_SEPARATOR.'data-structure.sql';
            $data = $extractPath.DIRECTORY_SEPARATOR.'data.sql';
            if (realpath($structure) && realpath($data)) {
                static::$statements = [];
                static::sqlImport($structure);
                static::sqlImport($data);
                static::create(md5_file($file), $v, $output);
                unlink($structure);
                unlink($data);
            } else {
                $output->writeln(PHP_EOL.sprintf('Error, cannot parse files in `%s` (file: `%s`), going deeper', $extractPath, $file));
                $application = new DataMergeCommand('Database merge tool');
                $application->run(new Console\Input\ArrayInput(['--folder' => $extractPath]), $output);
            }
        } else {
            $output->writeln(PHP_EOL.sprintf('Error %s opening archive `%s`', $zipStatus, $file));
        }
    }

    /**
     * @param string $sql
     *
     * @return bool
     */
    private static function isVersioningCreateStatement($sql)
    {
        if (strpos($sql, 'CREATE TABLE `'.static::$versioningTablePrefix) === 0) {
            return true;
        }

        return false;
    }

    /**
     * @param string $sql
     *
     * @return bool
     */
    private static function isVersioningInsertStatement($sql)
    {
        if (strpos($sql, 'INSERT INTO `'.static::$versioningTablePrefix) === 0) {
            return true;
        }

        return false;
    }

    /**
     * @param DBAL\Connection $cn
     * @param string          $table
     * @param string          $id
     * @param array           $values
     * @param array           $keys
     * @param string          $field
     *
     * @return int
     *
     * @throws \Exception
     */
    private static function insertDupe(DBAL\Connection $cn, $table, $id, $values, $keys, $field = 'id')
    {
        $key = isset($values[$field]) ? $field : isset($keys[$field], $values[$keys[$field]]) ? $keys[$field] : null;
        $values[$key] = $id.chr(rand(97, 122));
        try {
            return $cn->insert($table, $values);
        } catch (\Exception $e) {
            if (preg_match('/Duplicate entry \'(.+)\' for key \'PRIMARY\'/', $e->getMessage(), $matches) !== false) {
                return static::insertDupe($cn, $table, $id, $values, $keys);
            }
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param DBAL\Connection $cn
     * @param string          $sql
     * @param string          $msg
     * @param string          $table
     * @param string          $field
     *
     * @return array|false
     */
    private static function diffInsertTableStatement(DBAL\Connection $cn, $sql, $msg, $table, $field = 'id')
    {
        if ($table && strpos($msg, 'Duplicate entry') !== false && preg_match('/Duplicate entry \'(.+)\' for key \'PRIMARY\'/', $msg, $matches) !== false) {
            $pk = explode('-', $matches[1], count(static::$versioningTablePrimaryIndex));
            preg_match('/VALUES \((.+)\)/', $sql, $matches);
            $values = array_map(function ($_) {
                return trim($_, '\'');
            }, explode(',', $matches[1]));
            $format = 'SELECT * FROM `'.$table.'` WHERE %s = \''.implode('\' AND %s = \'', $pk).'\'';
            $result = $cn->fetchAssoc(call_user_func_array('sprintf', array_merge([$format], static::$versioningTablePrimaryIndex)));
            $diff = array_diff($values, array_values($result));
            $hasTimestamp = isset($diff[10]) && date_create_from_format('Y-m-d H:i:s.u', array_pop($diff)) !== false;
            if (count($diff) === 1 && $hasTimestamp) {
                return false;
            } elseif (count($diff) === 2 && isset($diff[3]) && $hasTimestamp) {
                return false;
            } elseif (count($diff) > 0) {
                return ['inserts' => static::insertDupe($cn, $table, $result[$field], $values, array_flip($result)), 'diff' => print_r($diff, true)];
            }

            return false;
        }
    }

    /**
     * @param string      $sql
     * @param string      $msg
     * @param string|null $table
     *
     * @return bool
     */
    private static function isSkipableStatement($sql, $msg, $table = null)
    {
        if ($table && in_array($table, static::$ignoredTables)) {
            return true;
        }
        foreach (static::$ignoredTablePrefixes as $ignoredTablePrefix) {
            if (strpos($sql, 'CREATE TABLE `'.$ignoredTablePrefix) === 0 || strpos($sql, 'INSERT INTO `'.$ignoredTablePrefix) === 0) {
                return true;
            }
        }
        foreach (static::$ignoredExceptionMessages as $ignoredSqlExceptionContain) {
            if (strpos($msg, $ignoredSqlExceptionContain) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string                         $token
     * @param string                         $build
     * @param Console\Output\OutputInterface $output
     */
    private static function create($token, $build, Console\Output\OutputInterface $output)
    {
        // @see https://github.com/doctrine/DoctrineBundle/blob/v1.5.2/Command/CreateDatabaseDoctrineCommand.php
        $dbTargetParams = \defDb::dbDist();
        /*$connTemporary = DBAL\DriverManager::getConnection($dbTargetParams);
        $dbTemporary = $dbTargetParams['dbname'].'_'.$token.'_'.str_replace('.', '_', $build);

        if (!in_array($dbTemporary, $connTemporary->getSchemaManager()->listDatabases())) {
            $output->writeln(PHP_EOL.sprintf('Creating temporary database `%s`...', $dbTemporary).PHP_EOL);
            // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
            unset($dbTargetParams['dbname'], $dbTargetParams['path'], $dbTargetParams['url']);
            $connTemporary->getSchemaManager()->createDatabase($dbTemporary);
        } else {
            $output->writeln(PHP_EOL.sprintf('Using already created temporary database `%s`...', $dbTemporary).PHP_EOL);
        }

        $dbTargetParams['dbname'] = $dbTemporary;
        $connTemporary->close();
        //$dbTargetParams['wrapperClass'] = 'Doctrine\DBAL\Driver\PDOConnection';*/
        static::injectStatements($conn = DBAL\DriverManager::getConnection($dbTargetParams), $token, $build, $output);
        $conn->close();
    }

    /**
     * @param DBAL\Connection                $connection
     * @param string                         $token
     * @param string                         $build
     * @param Console\Output\OutputInterface $output
     */
    private static function injectStatements(DBAL\Connection $connection, $token, $build, Console\Output\OutputInterface $output)
    {
        $output->write(PHP_EOL.sprintf('Executing %s statements...', count(static::$statements))."\t");
        if ($connection->getDatabasePlatform()->getName() === 'mysql') {
            $connection->executeQuery('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;');
            $sm = $connection->getSchemaManager();
            $injected = 0;
            $ignored = 0;
            $weird = 0;
            $errors = 0;
            foreach (static::$statements as $sql) {
                $hasItemName = preg_match('/`(\w+)`/', $sql, $matches) !== false;
                $name = $hasItemName ? $matches[1] : null;
                try {
                    if (strpos($name, static::$versioningTableNamePrefix) === 0) {
                        $oldName = $name;
                        $name = str_replace(static::$versioningTableNamePrefix, str_replace('.', '', $build, $count = 1).'_', $oldName, $count);
                        $sql = str_replace($oldName, $name, $sql, $count);
                    } elseif (static::isVersioningInsertStatement($sql)) {
                        //$sql = preg_replace('/^(INSERT INTO `'.static::$versioningTablePrefix.'\w+` VALUES \()(\'\w+\',)(\'\w+\-\w+\')(.+)/', '\\1\''.$token.'\',\''.$build.'\',\\2LOWER(\\3)\\4', $sql, 1);
                        preg_match('/^(INSERT INTO `'.static::$versioningTablePrefix.'\w+` VALUES \()(\'\w+\',)(\'\w+-\w+\')(.+)/', $sql, $matches);
                        $sql = $matches[1].'\''.$build.'\','.$matches[2].strtolower($matches[3]).',\''.$token.'\''.$matches[4];
                    }
                    // @see https://github.com/doctrine/dbal/blob/v2.5.12/lib/Doctrine/DBAL/Tools/Console/Command/ImportCommand.php
                    $stmt = $connection->prepare($sql);
                    $stmt->execute();
                    $stmt->closeCursor();
                    if (static::isVersioningCreateStatement($sql)) {
                        $tokenColumn = new DBAL\Schema\Column('token', DBAL\Types\Type::getType('string'), static::$commonColumnOptions);
                        $buildColumn = new DBAL\Schema\Column('build', DBAL\Types\Type::getType('string'), static::$commonColumnOptions);
                        $columns = $sm->listTableColumns($name);
                        $pkIndex = new DBAL\Schema\Index('pk', static::$versioningTablePrimaryIndex, false, true);
                        $sm->dropTable($name);
                        $sm->createTable(new DBAL\Schema\Table($name, array_merge([$tokenColumn, $buildColumn], $columns), [$pkIndex]));
                    }
                    ++$injected;
                    ++static::$totalInjected;
                } catch (\Exception $e) {
                    if (static::isSkipableStatement($sql, $e->getMessage(), $name)) {
                        ++$ignored;
                        ++static::$totalIgnored;
                    } elseif (is_array($diff = static::diffInsertTableStatement($connection, $sql, $e->getMessage(), $name))) {
                        $output->writeln(PHP_EOL.sprintf('Something weird found in `%s.%s`%sNew data-diff: %s%sInserts: %s%sLast ID: %s%sSQL: %s%s', $connection->getDatabase(), $name, PHP_EOL, $diff['diff'], PHP_EOL, $diff['inserts'], PHP_EOL, $connection->lastInsertId(), PHP_EOL, $sql, PHP_EOL."\t"));
                        ++$weird;
                        ++static::$totalWeird;
                    } elseif (!$diff) {
                        ++$ignored;
                        ++static::$totalIgnored;
                    } else {
                        $output->writeln(PHP_EOL.sprintf('Error found in `%s.%s`%sSQL: %s%sMessage: %s%s', $connection->getDatabase(), $name, PHP_EOL, substr($sql, 0, 450), PHP_EOL, $e->getMessage(), PHP_EOL."\t"));
                        ++$errors;
                        ++static::$totalErrors;
                    }
                    continue;
                }
            }
            $output->write(sprintf('%s/%s injects, %s/%s skips, %s/%s updates & %s/%s errors', $injected, static::$totalInjected, $ignored, static::$totalIgnored, $weird, static::$totalWeird, $errors, static::$totalErrors).PHP_EOL);
        }
    }

    /**
     * Import SQL from file
     *
     * @param string $file path to sql file
     */
    private static function sqlImport($file)
    {
        $delimiter = ';';
        $file = fopen($file, 'r');
        $isMultiLineComment = false;
        $sql = '';

        while (!feof($file)) {
            $row = fgets($file);
            // 1. ignore empty string, drops, locks and comment row
            if (trim($row) === '' || strpos($row, 'DROP TABLE') !== false || strpos($row, 'LOCK TABLE') !== false || preg_match('/^\s*(#|--\s|\/\*)/sUi', $row)) {
                continue;
            }
            // 2. clear comments
            $row = trim(static::clearSQL($row, $isMultiLineComment));
            // 3. parse delimiter row
            if (preg_match('/^DELIMITER\s+[^ ]+/sUi', $row)) {
                $delimiter = preg_replace('/^DELIMITER\s+([^ ]+)$/sUi', '$1', $row);
                continue;
            }
            // 4. separate sql queries by delimiter
            $offset = 0;
            while (strpos($row, $delimiter, $offset) !== false) {
                $delimiterOffset = strpos($row, $delimiter, $offset);
                if (static::isQuoted($delimiterOffset, $row)) {
                    $offset = $delimiterOffset + strlen($delimiter);
                } else {
                    $sql = trim($sql.' '.trim(substr($row, 0, $delimiterOffset)));
                    static::$statements[] = $sql;

                    $row = substr($row, $delimiterOffset + strlen($delimiter));
                    $offset = 0;
                    $sql = '';
                }
            }
            $sql = trim($sql.' '.$row);
        }
        if (strlen($sql) > 0) {
            static::$statements[] = $row;
        }

        fclose($file);
    }

    /**
     * Remove comments from sql
     *
     * @param string $sql
     * @param bool   $isMultiComment
     *
     * @return string
     */
    private static function clearSQL($sql, &$isMultiComment)
    {
        if ($isMultiComment) {
            if (preg_match('#\*/#sUi', $sql)) {
                $sql = preg_replace('#^.*\*/\s*#sUi', '', $sql);
                $isMultiComment = false;
            } else {
                $sql = '';
            }
            if (trim($sql) === '') {
                return $sql;
            }
        }

        $offset = 0;
        while (preg_match('{--\s|#|/\*[^!]}sUi', $sql, $matched, PREG_OFFSET_CAPTURE, $offset)) {
            list($comment, $foundOn) = $matched[0];
            if (static::isQuoted($foundOn, $sql)) {
                $offset = $foundOn + strlen($comment);
            } else {
                if (substr($comment, 0, 2) === '/*') {
                    $closedOn = strpos($sql, '*/', $foundOn);
                    if ($closedOn !== false) {
                        $sql = substr($sql, 0, $foundOn).substr($sql, $closedOn + 2);
                    } else {
                        $sql = substr($sql, 0, $foundOn);
                        $isMultiComment = true;
                    }
                } else {
                    $sql = substr($sql, 0, $foundOn);
                    break;
                }
            }
        }

        return $sql;
    }

    /**
     * Check if "offset" position is quoted
     *
     * @param int    $offset
     * @param string $text
     *
     * @return bool
     */
    private static function isQuoted($offset, $text)
    {
        if ($offset > strlen($text)) {
            $offset = strlen($text);
        }

        $isQuoted = false;
        for ($i = 0; $i < $offset; ++$i) {
            if ($text[$i] === "'") {
                $isQuoted = !$isQuoted;
            }
            if ($text[$i] === '\\' && $isQuoted) {
                ++$i;
            }
        }

        return $isQuoted;
    }
}
