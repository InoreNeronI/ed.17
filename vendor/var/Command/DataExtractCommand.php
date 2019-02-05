<?php

namespace Command;

use Doctrine\DBAL;
use Symfony\Component\Console;

class DataExtractCommand extends Console\Command\Command
{
    public static $baseBuild = '0.5';
    public static $extractedPath = null;
    private static $ignoredIdColumnPrefixes = ['ed17-900'];
    private static $pdoErrors = [];
    private static $pregDupe = '/Duplicate entry \'(.`+)\' for key \'PRIMARY\'/';
    private static $statementsStructure = [];
    private static $statements = [];
    public static $totalCreated = 0;
    public static $totalErrors = 0;
    public static $totalIgnored = 0;
    public static $totalInserted = 0;
    public static $totalUpdated = 0;
    private static $versioningTableNamePrefix = 'edg051_testuak_';
    private static $versioningTablePrefix = 'erantzunak_';
    public static $versioningTablePrimaryIndex = ['build', 'code', 'id'];

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
        static::$extractedPath = sys_get_temp_dir().DIRECTORY_SEPARATOR;
        static::$extractedPath .= DataMergeCommand::$filesCurrentFolder ? basename(DataMergeCommand::$filesCurrentFolder) : 'extracted';
        if (true === $zipStatus) {
            if (!$zip->setPassword($pw) || !$zip->extractTo(static::$extractedPath)) {
                throw new \Exception(sprintf('Error, extraction of `%s` failed (wrong `%s` password?).', $file, $pw));
            }
            $zip->close();
            $structure = static::$extractedPath.DIRECTORY_SEPARATOR.'data-structure.sql';
            $data = static::$extractedPath.DIRECTORY_SEPARATOR.'data.sql';
            if (realpath($structure) && realpath($data)) {
                static::$statements = array_merge(static::sqlImport($structure), static::sqlImport($data));
                static::create(md5_file($file), $build, $output);
                unlink($structure);
                unlink($data);
            } else {
                $output->writeln(PHP_EOL.sprintf('Error, cannot parse files in `%s` (file: `%s`), going deeper', static::$extractedPath, $file));
                $application = new DataMergeCommand('Database merge tool');
                $application->run(new Console\Input\ArrayInput(['--folder' => static::$extractedPath]), $output);
            }
        } else {
            $output->writeln(PHP_EOL.sprintf('Error %s opening archive `%s`', $zipStatus, str_replace(DataMergeCommand::$filesSourcePath.DIRECTORY_SEPARATOR, '', $file)));
            ++static::$totalErrors;
        }
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
    private static function diffInsertParser(DBAL\Connection $cn, $sql, $name, $msg)
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
     * @param DBAL\Connection                $cn
     * @param string                         $name
     * @param string                         $token
     * @param string                         $build
     * @param string                         $sql
     * @param string                         $msg
     * @param Console\Output\OutputInterface $output
     *
     * @return bool
     */
    private static function diffInsertStatement(DBAL\Connection $cn, $name, $token, $build, $sql, $msg, Console\Output\OutputInterface $output)
    {
        if (0 === strpos($name, static::$versioningTablePrefix)) {
            if (is_array($diff = static::diffInsertHandler($cn, $sql, $msg, $name))) {
                $output->writeln($diff['output']);
                ++static::$totalUpdated;

                return true;
            } elseif (false === $diff) {
                ++static::$totalIgnored;

                return true;
            }
        }
        if (false !== strpos($msg, '1136 Column count')) {
            DataMergeCommand::runStatement($cn, str_replace($name, $tmpName = $name.'_'.uniqid(), static::$statementsStructure[$name]));
            $sm = $cn->getSchemaManager();
            if (false !== strpos($tmpName, static::$versioningTablePrefix)) {
                $tmpTable = DataMergeCommand::getVersionedTableObj($sm->listTableColumns($tmpName), $tmpName);
                $sm->dropTable($tmpName);
                $sm->createTable($tmpTable);
            }
            $oldColumns = $sm->listTableColumns($name);
            $newColumns = $sm->listTableColumns($tmpName);
            $columns = [];
            foreach ($columnsDiff = array_diff(array_keys($newColumns), array_keys($oldColumns)) as $diff) {
                array_push($columns, new DBAL\Schema\Column($diff, $newColumns[$diff]->getType(), $newColumns[$diff]->toArray()));
            }
            $sm->dropTable($tmpName);
            if (count($newColumns) > count($oldColumns)) {
                $output->write(sprintf('Adding %s columns in `%s.%s` -> %s', count($columnsDiff), $cn->getDatabase(), $name, PHP_EOL.PHP_EOL."\t".implode(', ', $columnsDiff).PHP_EOL.PHP_EOL."\t\t\t\t"));
                $sm->alterTable(new DBAL\Schema\TableDiff($name, $columns));

                return static::injectStatement($cn, $sql, $token, $build, $output);
            }

            return true;
        }

        return false;
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
    private static function diffInsertHandler(DBAL\Connection $cn, $sql, $msg, $name, $field = 'id')
    {
        if (preg_match(static::$pregDupe, $msg, $_matches)) {
            list($values, $result, $diff) = static::diffInsertParser($cn, $sql, $name, $_matches[1]);
            $popsTimestamp = isset($diff[10]) && date_create_from_format('Y-m-d H:i:s.u', $diff[10]) instanceof \DateTime;

            if (2 === count($diff) && isset($diff[3]) && $popsTimestamp) {
                return false;
            } elseif (1 === count($diff) && isset($diff[3]) || $popsTimestamp) {
                return false;
            } elseif (count($diff) > 0 && $popsTimestamp &&
                is_int($inserts = static::insertDupe($cn, $name, $result[$field], $values, array_flip($keys = array_keys($result))))) {
                foreach ($diff as $k => $field) {
                    unset($diff[$k]);
                    $diff[$keys[$k]] = $field;
                }

                return ['output' => PHP_EOL.PHP_EOL.sprintf(
                'Differences found in `%s.%s`%sInserted %s row(s), added-diff: %s',
                        $cn->getDatabase(), $name, PHP_EOL.PHP_EOL, $inserts, DataMergeCommand::printArray($diff).PHP_EOL)];
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
        if ('mysql' === $cn->getDatabasePlatform()->getName()) {
            $cn->executeQuery('SET NAMES utf8 COLLATE utf8_unicode_ci;');
            foreach (static::$statements as $sql) {
                static::injectStatement($cn, $sql, $token, $build, $output);
            }
            $output->writeln(sprintf('%s creates, %s inserts, %s skips, %s updates & %s errors', static::$totalCreated, static::$totalInserted, static::$totalIgnored, static::$totalUpdated, static::$totalErrors));
        }
        $cn->close();
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
        if (0 === strpos($name = $_matches[1], static::$versioningTableNamePrefix)) {
            $oldName = $name;
            $name = str_replace(static::$versioningTableNamePrefix, str_replace('.', '', $build).'_', $oldName);
            $sql = str_replace($oldName, $name, $sql);
        }
        try {
            if (0 === strpos($sql, 'CREATE TABLE `')) {
                static::$statementsStructure[$name] = $sql;
                DataMergeCommand::runStatement($cn, $sql);
                if (false !== strpos($name, static::$versioningTablePrefix)) {
                    $sm = $cn->getSchemaManager();
                    $table = DataMergeCommand::getVersionedTableObj($sm->listTableColumns($name), $name);
                    $sm->dropTable($name);
                    $sm->createTable($table);
                }
                ++static::$totalCreated;
            } elseif (false !== preg_match('/^(INSERT INTO `'.static::$versioningTablePrefix.'\w+` VALUES )(.+)$/', $sql, $_matches)) {
                $curatedSql = preg_replace('/(\)\s*,\s*\()/', '{#}', substr($_matches[2], 1, -1));
                $curatedInserts = explode('{#}', $curatedSql);
                foreach (static::$ignoredIdColumnPrefixes as $ignoredIdColumnPrefix) {
                    foreach ($curatedInserts as $insert) {
                        if (preg_match('/^(\'\w+\',)(\'\w+-\w+\')(.+)/', $insert, $__matches)) {
                            if (0 === strpos($id = strtolower($__matches[2]), '\''.$ignoredIdColumnPrefix)) {
                                ++static::$totalIgnored;
                                continue;
                            }
                            $sql = $_matches[1].'(\''.$build.'\','.$__matches[1].$id.',\''.$token.'\''.$__matches[3].');';
                        }
                        DataMergeCommand::runStatement($cn, $sql);
                        ++static::$totalInserted;
                    }
                }
                if (empty($curatedInserts) || empty(static::$ignoredIdColumnPrefixes)) {
                    DataMergeCommand::runStatement($cn, $sql);
                    ++static::$totalInserted;
                }
            } elseif (0 === strpos($sql, 'INSERT INTO `')) {
                DataMergeCommand::runStatement($cn, $sql);
                ++static::$totalInserted;
            } elseif (0 === strpos($sql, 'UPDATE `')) {
                DataMergeCommand::runStatement($cn, $sql);
                ++static::$totalUpdated;
            }

            return true;
        } catch (\Exception $e) {
            if (static::diffInsertStatement($cn, $name, $token, $build, $sql, $msg = $e->getMessage(), $output)) {
                return true;
            } elseif (DataMergeCommand::isSkipableStatement($sql, $msg, $name)) {
                ++static::$totalIgnored;

                return true;
            } elseif ($logPath = static::isPDOException($msg)) {
                if (!isset(static::$pdoErrors[$logPath])) {
                    $output->write($curatedMsg = sprintf('Error, see `%s`', $logPath).PHP_EOL."\t\t\t\t");
                    ++static::$totalErrors;
                    static::$pdoErrors[$logPath] = $curatedMsg;
                }

                return true;
            }
            $output->writeln(PHP_EOL.PHP_EOL.sprintf('Error found in `%s.%s`%sWith message: %s', $cn->getDatabase(), $name, PHP_EOL.PHP_EOL, $msg.PHP_EOL));
            ++static::$totalErrors;
            //dump($sql);
            throw $e;
        }

        return false;
    }

    /**
     * @param string $msg
     *
     * @return string|false
     */
    private static function isPDOException($msg)
    {
        return false !== strpos($msg, 'SQLSTATE') ? DataMergeCommand::backupAndLog($msg) : false;
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
            if ('' === trim($row) || false !== strpos($row, 'DROP TABLE') || false !== strpos($row, 'LOCK TABLE') || preg_match('/^\s*(#|--\s|\/\*)/sUi', $row)) {
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
            while (false !== strpos($row, $delimiter, $offset)) {
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
            if ('' === trim($sql)) {
                return $sql;
            }
        }
        $offset = 0;
        while (preg_match('{--\s|#|/\*[^!]}sUi', $sql, $matched, PREG_OFFSET_CAPTURE, $offset)) {
            list($comment, $foundOn) = $matched[0];
            if (static::isQuoted($foundOn, $sql)) {
                $offset = $foundOn + strlen($comment);
            } else {
                if ('/*' === substr($comment, 0, 2)) {
                    $closedOn = strpos($sql, '*/', $foundOn);
                    if (false !== $closedOn) {
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
            if ("'" === $text[$i]) {
                $isQuoted = !$isQuoted;
            }
            if ('\\' === $text[$i] && $isQuoted) {
                ++$i;
            }
        }

        return $isQuoted;
    }
}
