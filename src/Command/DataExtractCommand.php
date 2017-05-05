<?php

namespace Command;

use Doctrine\DBAL;
use Symfony\Component\Console;

class DataExtractCommand extends Console\Command\Command
{
    private static $baseBuild = '0.5';
    private static $ignoredExceptionMessages = ['Base table or view already exists'];
    private static $ignoredIdPrefix = 'ed17-900';
    private static $ignoredTables = ['edg051_testuak_dbh_simul', 'erantzunak_dbh_simul'];
    private static $ignoredTablePrefixes = ['05_', '10_', '20_', '30_', 'ikasleak'];
    private static $pregDupe = '/Duplicate entry \'(.+)\' for key \'PRIMARY\'/';
    private static $statementsStructure = [];
    private static $statements = [];
    public static $totalCreated = 0;
    public static $totalErrors = 0;
    public static $totalIgnored = 0;
    public static $totalInserted = 0;
    public static $totalUpdated = 0;
    private static $versioningTableNamePrefix = 'edg051_testuak_';
    private static $versioningTablePrefix = 'erantzunak_';
    private static $versioningTablePrimaryIndex = ['build', 'code', 'id'];

    /**
     * @param array $arr
     *
     * @return mixed
     */
    private static function printArray($arr)
    {
        return str_replace('Array', '', str_replace('(', '[', str_replace(')', ']', print_r($arr, true))));
    }

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
        $build = $input->getOption('version');
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
                static::$statements = array_merge(static::sqlImport($structure), static::sqlImport($data));
                static::create(md5_file($file), $build, $output);
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
     * @see https://github.com/doctrine/dbal/blob/v2.5.12/lib/Doctrine/DBAL/Tools/Console/Command/ImportCommand.php
     *
     * @param DBAL\Connection $cn
     * @param string          $sql
     *
     * @return bool
     */
    private static function runStatement(DBAL\Connection $cn, $sql)
    {
        $stmt = $cn->prepare($sql);
        $run = $stmt->execute();
        $stmt->closeCursor();

        return $run;
    }

    /**
     * @param DBAL\Connection $cn
     * @param string          $name
     * @param string          $id
     * @param array           $values
     * @param array           $keys
     * @param string          $field
     *
     * @return int
     *
     * @throws \Exception
     */
    private static function insertDupe(DBAL\Connection $cn, $name, $id, $values, $keys, $field = 'id')
    {
        if ($key = isset($values[$field]) ? $field : isset($keys[$field], $values[$keys[$field]]) ? $keys[$field] : null) {
            $values[$key] = $id.chr(rand(97, 122));
            try {
                return $cn->insert($name, array_combine(array_keys($keys), $values));
            } catch (\Exception $e) {
                if (preg_match(static::$pregDupe, $e->getMessage())) {
                    return static::insertDupe($cn, $name, $id, $values, $keys, $field);
                }
                throw new \Exception($e->getMessage(), $e->getCode(), $e);
            }
        } else {
            throw new \Exception(sprintf('Invalid `%s` key.'), $key);
        }
    }

    /**
     * @param DBAL\Connection $cn
     * @param string          $sql
     * @param string          $name
     * @param string          $msg
     *
     * @return array
     */
    private static function diffInsert(DBAL\Connection $cn, $sql, $name, $msg)
    {
        preg_match('/VALUES \((.+)\)/', $sql, $_matches);
        $pk = explode('-', $msg, count(static::$versioningTablePrimaryIndex));
        $format = 'SELECT * FROM `'.$name.'` WHERE `%s` = \''.implode('\' AND `%s` = \'', $pk).'\'';
        $result = $cn->fetchAssoc(call_user_func_array('sprintf', array_merge([$format], static::$versioningTablePrimaryIndex)));
        $values = array_map(function ($_) {
            return trim($_, 'NULL');
        }, str_getcsv($_matches[1], ',', '\''));
        return [$values, $result, array_diff($values, array_values($result))];
    }

    /**
     * @param DBAL\Connection $cn
     * @param string          $sql
     * @param string          $msg
     * @param string          $name
     * @param Console\Output\OutputInterface $output
     *
     * @return bool
     */
    private static function diffInsertColumnsStatement(DBAL\Connection $cn, $sql, $msg, $name, Console\Output\OutputInterface $output)
    {
        if (strpos($msg, '1136 Column count') !== false) {
            static::runStatement($cn, str_replace($name, $tmpName = $name.'_'.uniqid(), static::$statementsStructure[$name]));
            $sm = $cn->getSchemaManager();
            if (strpos($tmpName, static::$versioningTablePrefix) !== false) {
                $tmpTable = static::versionTableObj($sm->listTableColumns($tmpName), $tmpName);
                $sm->dropTable($tmpName);
                $sm->createTable($tmpTable);
            }
            $oldColumns = $sm->listTableColumns($name);
            $newColumns = $sm->listTableColumns($tmpName);
            $sm->dropTable($tmpName);

            if (count($newColumns) > count($oldColumns)) {
                $columns = [];
                foreach ($columnsDiff = array_diff(array_keys($newColumns), array_keys($oldColumns)) as $diff) {
                    array_push($columns, new DBAL\Schema\Column($diff, $newColumns[$diff]->getType(), $newColumns[$diff]->toArray()));
                }
                if (!empty($columnsDiff)) {
                    $output->write(sprintf('Adding %s columns in `%s.%s` -> %s', count($columnsDiff), $cn->getDatabase(), $name, PHP_EOL.PHP_EOL."\t".implode(', ', $columnsDiff).PHP_EOL.PHP_EOL."\t\t\t\t"));
                    $sm->alterTable(new DBAL\Schema\TableDiff($name, $columns));
                    try {
                        static::runStatement($cn, $sql);
                    } catch (\Exception $e) {
                        return static::diffInsertTableStatement($cn, $sql, $e->getMessage(), $name, $output);
                    }
                }
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * @param DBAL\Connection $cn
     * @param string          $sql
     * @param string          $msg
     * @param string          $name
     * @param Console\Output\OutputInterface $output
     *
     * @return bool
     */
    private static function diffInsertTableStatement(DBAL\Connection $cn, $sql, $msg, $name, Console\Output\OutputInterface $output)
    {
        if (strpos($name, static::$versioningTablePrefix) === 0) {
            $diff = static::diffColumnTable($cn, $sql, $msg, $name);
            if (is_array($diff)) {
                $output->writeln($diff['output']);
                ++static::$totalUpdated;
                return true;
            } elseif ($diff === false) {
                ++static::$totalIgnored;
                return true;
            }
        }

        return static::diffInsertColumnsStatement($cn, $sql, $msg, $name, $output);
    }

    /**
     * @param DBAL\Connection $cn
     * @param string          $sql
     * @param string          $msg
     * @param string          $name
     * @param string          $field
     *
     * @return array|false
     */
    private static function diffColumnTable(DBAL\Connection $cn, $sql, $msg, $name, $field = 'id')
    {
        if (preg_match(static::$pregDupe, $msg, $_matches)) {
            list($values, $result, $diff) = static::diffInsert($cn, $sql, $name, $_matches[1]);
            $popsTimestamp = isset($diff[10]) && date_create_from_format('Y-m-d H:i:s.u', $diff[10]) instanceof \DateTime;

            if (count($diff) === 2 && isset($diff[3]) && $popsTimestamp) {
                return false;
            } elseif (count($diff) === 1 && isset($diff[3]) || $popsTimestamp) {
                return false;
            } elseif (count($diff) > 0 && $popsTimestamp && is_int($inserts = static::insertDupe($cn, $name, $result[$field], $values, array_flip($keys = array_keys($result))))) {
                foreach ($d = $diff as $k => $field) {
                    unset($diff[$k]);
                    $diff[$keys[$k]] = $field;
                }

                return ['diff' => $diff = static::printArray($diff), 'output' => PHP_EOL.PHP_EOL.sprintf(
                'Diff found in `%s.%s`%sInserted %s row(s), diff: %s',
                        $cn->getDatabase(),
                        $name,
                        PHP_EOL.PHP_EOL,
                        $inserts,
                        $diff.PHP_EOL
                    )];
            }

            return false;
        }
    }

    /**
     * @param string          $sql
     * @param string          $msg
     * @param string|null     $name
     *
     * @return bool
     */
    private static function isSkipableStatement($sql, $msg, $name = null)
    {
        if ($name && in_array($name, static::$ignoredTables)) {
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
        /*$cnTemporary = DBAL\DriverManager::getConnection($dbTargetParams);
        $dbTemporary = $dbTargetParams['dbname'].'_'.$token.'_'.str_replace('.', '_', $build);

        if (!in_array($dbTemporary, $cnTemporary->getSchemaManager()->listDatabases())) {
            $output->writeln(PHP_EOL.sprintf('Creating temporary database `%s`...', $dbTemporary).PHP_EOL);
            // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
            unset($dbTargetParams['dbname'], $dbTargetParams['path'], $dbTargetParams['url']);
            $cnTemporary->getSchemaManager()->createDatabase($dbTemporary);
        } else {
            $output->writeln(PHP_EOL.sprintf('Using already created temporary database `%s`...', $dbTemporary).PHP_EOL);
        }
        $dbTargetParams['dbname'] = $dbTemporary;
        $cnTemporary->close();
        //$dbTargetParams['wrapperClass'] = 'Doctrine\DBAL\Driver\PDOConnection';*/
        $output->write(PHP_EOL.sprintf('Processing %s statements...', count(static::$statements))."\t");
        $cn = DBAL\DriverManager::getConnection($dbTargetParams);
        if ($cn->getDatabasePlatform()->getName() === 'mysql') {
            $cn->executeQuery('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;');
            foreach (static::$statements as $sql) {
                static::injectStatement($cn, $sql, $token, $build, $output);
            }
            $output->writeln(sprintf('%s inserts, %s creates, %s skips, %s updates & %s errors', static::$totalInserted, static::$totalCreated, static::$totalIgnored, static::$totalUpdated, static::$totalErrors));
        }
        $cn->close();
    }

    /**
     * @param DBAL\Connection                $cn
     * @param array                          $columns
     * @param string                         $name
     * @param Console\Output\OutputInterface $output
     */
    /*private static function mergeColumns(DBAL\Connection $cn, $columns, $name, Console\Output\OutputInterface $output)
    {
        $output->write(sprintf('Merging `%s` columns...', $name));
        if ($cn->getDatabasePlatform()->getName() === 'mysql') {

            $schemaManager = $cn->getSchemaManager();
            // @see http://www.craftitonline.com/2014/09/doctrine-migrations-with-schema-api-without-symfony-symfony-cmf-seobundle-sylius-example
            $nameDiffColumns = [];
            foreach ($columns as $column) {
                if (!array_search($column, array_keys($schemaManager->listTableColumns($name)))) {
                    $nameDiffColumns[] = new DBAL\Schema\Column($column, DBAL\Types\Type::getType('string'), ['length' => 255, 'notnull' => false]);
                    $output->write(PHP_EOL."\t"."\t".sprintf('`%s`...', $column));
                }
            }
            $output->writeln('');
            $schemaManager->alterTable(new DBAL\Schema\TableDiff($name, $nameDiffColumns));
        }
    }*/

    /**
     * @param string $name
     * @param array $columns
     * @param array $indexes
     *
     * @return DBAL\Schema\Table
     */
    private static function getTableObj($name, $columns, $indexes = [])
    {
        $pkIndex = new DBAL\Schema\Index('pk', static::$versioningTablePrimaryIndex, false, true);
        return new DBAL\Schema\Table($name, $columns, array_merge([$pkIndex], $indexes));
    }

    /**
     * @param array $columns
     * @param string                            $name
     * @param array                             $extraFields
     * @param array                             $extraTypes
     * @param array                             $extraCommonProperties
     * @return                                  DBAL\Schema\Table
     * @throws                                  \Exception
     */
    private static function versionTableObj($columns, $name, $extraFields = ['token', 'build'], $extraTypes = ['string', 'string'], $extraCommonProperties = ['length' => 40, 'notnull' => true])
    {
        if (count($fields = array_values($extraFields)) !== count($types = array_values($extraTypes))) {
            throw new \Exception('Fields and types amount mismatch');
        }
        $cols = [];
        foreach ($fields as $k => $field) {
            array_push($cols, new DBAL\Schema\Column($field, DBAL\Types\Type::getType($types[$k]), $extraCommonProperties));
        }
        return static::getTableObj($name, array_merge($cols, $columns));
    }

    /**
     * @param DBAL\Connection                $cn
     * @param string                         $sql
     * @param string                         $token
     * @param string                         $build
     * @param Console\Output\OutputInterface $output
     *
     * @return bool
     *
     * @throws \Exception
     */
    private static function injectStatement(DBAL\Connection $cn, $sql, $token, $build, Console\Output\OutputInterface $output)
    {
        preg_match('/`(\w+)`/', $sql, $_matches);
        if (strpos($name = $_matches[1], static::$versioningTableNamePrefix) === 0) {
            $oldName = $name;
            $name = str_replace(static::$versioningTableNamePrefix, str_replace('.', '', $build).'_', $oldName);
            $sql = str_replace($oldName, $name, $sql);
        }
        try {
            if (strpos($sql, 'CREATE TABLE `') === 0) {
                static::$statementsStructure[$name] = $sql;
                static::runStatement($cn, $sql);
                if (strpos($name, static::$versioningTablePrefix) !== false) {
                    $sm = $cn->getSchemaManager();
                    $table = static::versionTableObj($sm->listTableColumns($name), $name);
                    $sm->dropTable($name);
                    $sm->createTable($table);
                }
                ++static::$totalCreated;
            } elseif (preg_match('/^(INSERT INTO `'.static::$versioningTablePrefix.'\w+` VALUES )(.+)$/', $sql, $_matches) !== false) {
                $curatedSql = preg_replace('/(\)\s*,\s*\()/', '{#}', substr($_matches[2], 1, -1));
                $curatedInserts = explode('{#}', $curatedSql);
                foreach ($curatedInserts as $insert) {
                    if (preg_match('/^(\'\w+\',)(\'\w+-\w+\')(.+)/', $insert, $__matches)) {
                        if (strpos($id = strtolower($__matches[2]), '\''.static::$ignoredIdPrefix) === 0) {
                            ++static::$totalIgnored;
                            continue;
                        }
                        $sql = $_matches[1].'(\''.$build.'\','.$__matches[1].$id.',\''.$token.'\''.$__matches[3].');';
                    }
                    static::runStatement($cn, $sql);
                    ++static::$totalInserted;
                }
            } elseif (strpos($sql, 'INSERT INTO `') === 0) {
                static::runStatement($cn, $sql);
                ++static::$totalInserted;
            } elseif (strpos($sql, 'UPDATE `') === 0) {
                static::runStatement($cn, $sql);
                ++static::$totalUpdated;
            }
            return true;
        } catch (\Exception $e) {
            if (static::diffInsertColumnsStatement($cn, $sql, $e->getMessage(), $name, $output) ||
                static::diffInsertTableStatement($cn, $sql, $e->getMessage(), $name, $output)) {
                return true;
            } elseif (static::isSkipableStatement($sql, $e->getMessage(), $name)) {
                ++static::$totalIgnored;
                return true;
            } else {
                $output->writeln(PHP_EOL.PHP_EOL.sprintf('Error found in `%s.%s`%sWith message: %s%s', $cn->getDatabase(), $name, PHP_EOL.PHP_EOL, $e->getMessage(), PHP_EOL));
                ++static::$totalErrors;
                dump($sql);
                throw $e;
            }
        }
        return false;
    }

    /**
     * Import SQL from file
     *
     * @param string $file path to sql file
     *
     * @return array
     */
    private static function sqlImport($file)
    {
        $delimiter = ';';
        $file = fopen($file, 'r');
        $isMultiLineComment = false;
        $statements = [];
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
                    $statements[] = $sql;

                    $row = substr($row, $delimiterOffset + strlen($delimiter));
                    $offset = 0;
                    $sql = '';
                }
            }
            $sql = trim($sql.' '.$row);
        }
        if (strlen($sql) > 0) {
            $statements[] = $row;
        }

        fclose($file);

        return $statements;
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
