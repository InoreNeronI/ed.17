<?php

namespace Command;

use Doctrine\DBAL;
use Symfony\Component\Console;

class DataExtractCommand extends Console\Command\Command
{
    private static $baseBuild = '0.5';

    private static $ignoredExceptionMessages = ['Base table or view already exists'];

    private static $ignoredTables = ['edg051_testuak_dbh_simul', 'erantzunak_dbh_simul'];

    private static $ignoredTablePrefixes = ['05_', '10_', '20_', '30_'];

    private static $pregDupe = '/Duplicate entry \'(.+)\' for key \'PRIMARY\'/';
    private static $statements = [];
    public static $totalCreated = 0;
    public static $totalErrors = 0;
    public static $totalIgnored = 0;
    public static $totalInserted = 0;
    public static $totalWeird = 0;

    private static $versioningTableNamePrefix = 'edg051_testuak_';

    private static $versioningTablePrefix = 'erantzunak_';

    private static $versioningTablePrimaryIndex = ['build', 'code', 'id'];

    /**
     * @param array $arr
     * @return mixed
     */
    private static function beautifyArray($arr)
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
     * @see https://github.com/doctrine/dbal/blob/v2.5.12/lib/Doctrine/DBAL/Tools/Console/Command/ImportCommand.php
     *
     * @param DBAL\Connection $cn
     * @param string $sql
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
        if ($key = isset($values[$field]) ? $field : isset($keys[$field], $values[$keys[$field]]) ? $keys[$field] : null) {
            $values[$key] = $id.chr(rand(97, 122));
            try {
                return $cn->insert($table, array_combine(array_keys($keys), $values));
            } catch (\Exception $e) {
                if (preg_match(static::$pregDupe, $e->getMessage())) {
                    return static::insertDupe($cn, $table, $id, $values, $keys, $field);
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
     * @param string          $msg
     * @param string          $table
     * @param string          $field
     *
     * @return array|false
     */
    private static function diffInsertTableStatement(DBAL\Connection $cn, $sql, $msg, $table, $field = 'id')
    {
        if (preg_match(static::$pregDupe, $msg, $matches)) {
            $pk = explode('-', $matches[1], count(static::$versioningTablePrimaryIndex));
            preg_match('/VALUES \((.+)\)/', $sql, $matches);
            $values = array_map(function ($_) {
                return trim($_, 'NULL');
            }, str_getcsv($matches[1], ',', '\''));

            $format = 'SELECT * FROM `'.$table.'` WHERE `%s` = \''.implode('\' AND `%s` = \'', $pk).'\'';
            $result = $cn->fetchAssoc(call_user_func_array('sprintf', array_merge([$format], static::$versioningTablePrimaryIndex)));
            $diff = array_diff($values, array_values($result));
            $popsTimestamp = isset($diff[10]) && date_create_from_format('Y-m-d H:i:s.u', array_pop($diff)) !== false;

            if (count($diff) === 1 && isset($diff[3]) || $popsTimestamp) {
                return false;
            } elseif (count($diff) === 2 && isset($diff[3]) && $popsTimestamp) {
                return false;
            } elseif (count($diff) > 0 && is_int($inserts = static::insertDupe($cn, $table, $result[$field], $values, array_flip($keys = array_keys($result))))) {
                foreach ($d = $diff as $k => $field) {
                    unset($diff[$k]);
                    $diff[$keys[$k]] = $field;
                }

                return ['inserts' => $inserts, 'diff' => static::beautifyArray($diff)];
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
        /*if ($table && strpos($msg, '1136 Column count') !== false) {
            static::mergeColumns($cn, $table)
                        dump($e->getMessage());
                        dump($diff);
        } else*/if ($table && in_array($table, static::$ignoredTables)) {
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
     * @param DBAL\Connection                $cn
     * @param array                          $columns
     * @param string                         $table
     * @param Console\Output\OutputInterface $output
     */
    private static function mergeColumns(DBAL\Connection $cn, $columns, $table, Console\Output\OutputInterface $output)
    {
        $output->write(PHP_EOL.PHP_EOL."\t".sprintf('Merging `%s` columns...', $table));
        if ($cn->getDatabasePlatform()->getName() === 'mysql') {

            $schemaManager = $cn->getSchemaManager();
            // @see http://www.craftitonline.com/2014/09/doctrine-migrations-with-schema-api-without-symfony-symfony-cmf-seobundle-sylius-example
            $tableDiffColumns = [];
            foreach ($columns as $column) {
                if (!array_search($column, array_keys($schemaManager->listTableColumns($table)))) {
                    $tableDiffColumns[] = new DBAL\Schema\Column($column, DBAL\Types\Type::getType('string'), ['length' => 255, 'notnull' => false]);
                    $output->write(PHP_EOL."\t"."\t".sprintf('`%s`...', $column));
                }
            }
            $output->writeln('');
            $schemaManager->alterTable(new DBAL\Schema\TableDiff($table, $tableDiffColumns));
        }
    }

    /**
     * @param DBAL\Connection                $cn
     * @param string                         $token
     * @param string                         $build
     * @param Console\Output\OutputInterface $output
     */
    private static function injectStatements(DBAL\Connection $cn, $token, $build, Console\Output\OutputInterface $output)
    {
        $output->write(PHP_EOL.sprintf('Executing %s statements...', count(static::$statements))."\t");
        $platform = $cn->getDatabasePlatform();
        if ($platform->getName() === 'mysql') {
            $cn->executeQuery('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;');
            $sm = $cn->getSchemaManager();
            $created = 0;
            $errors = 0;
            $ignored = 0;
            $inserted = 0;
            $weird = 0;
            foreach (static::$statements as $sql) {
                preg_match('/`(\w+)`/', $sql, $matches);
                $name = $matches[1];
                try {
                    if (strpos($name, static::$versioningTableNamePrefix) === 0) {
                        $oldName = $name;
                        $name = str_replace(static::$versioningTableNamePrefix, str_replace('.', '', $build, $count = 1).'_', $oldName, $count);
                        $sql = str_replace($oldName, $name, $sql, $count);
                    }
                    /*if (strpos($sql, 'CREATE TABLE `'.static::$versioningTablePrefix) === 0) {
                        $cn->getSchemaManager()->createSchemaConfig()->setDefaultTableOptions()
                        $newSequence = new DBAL\Schema\Sequence()
                        $newSchema = new DBAL\Schema\Schema()
                        DBAL\Schema\Comparator::compareSchemas()
                    } else*/if (strpos($sql, 'CREATE TABLE `') === 0) {
                        static::runStatement($cn, $sql);
                        ++$created;
                        ++static::$totalCreated;
                    } elseif (strpos($sql, 'INSERT INTO `'.static::$versioningTablePrefix) === 0) {
                        preg_match('/^(INSERT INTO `\w+` VALUES )(.+)$/', $sql, $matches);
                        preg_match_all('/\(([^\)]+)\)/', $matches[2], $sonMatches);
                        for ($i = 1; $i < count($sonMatches[1]); $i++) {
                            preg_match('/^(\'\w+\',)(\'\w+-\w+\')(.+)$/', $sonMatches[1][$i], $grandSonMatches);
                            $sql = $matches[1].'(\''.$build.'\','.$grandSonMatches[1].strtolower($grandSonMatches[2]).',\''.$token.'\''.$grandSonMatches[3].')';
                            static::runStatement($cn, $sql);
                            ++$inserted;
                            ++static::$totalInserted;
                        }
                    } elseif (strpos($sql, 'INSERT INTO `') === 0) {
                        static::runStatement($cn, $sql);
                        ++$inserted;
                        ++static::$totalInserted;
                    } elseif (strpos($sql, 'UPDATE `') === 0) {
                        static::runStatement($cn, $sql);
                        ++$weird;
                        ++static::$totalWeird;
                    }
                    if (strpos($sql, 'CREATE TABLE `'.static::$versioningTablePrefix) === 0) {
                        $tokenColumn = new DBAL\Schema\Column('token', DBAL\Types\Type::getType('string'), ['length' => 40, 'notnull' => true]);
                        $buildColumn = new DBAL\Schema\Column('build', DBAL\Types\Type::getType('string'), ['length' => 40, 'notnull' => true]);
                        $columns = $sm->listTableColumns($name);
                        $pkIndex = new DBAL\Schema\Index('pk', static::$versioningTablePrimaryIndex, false, true);
                        $sm->dropTable($name);
                        $sm->createTable(new DBAL\Schema\Table($name, array_merge([$tokenColumn, $buildColumn], $columns), [$pkIndex]));
                    }
                } catch (\Exception $e) {
                    if (static::isSkipableStatement($sql, $e->getMessage(), $name)) {
                        ++$ignored;
                        ++static::$totalIgnored;
                    } elseif (is_array($diff = static::diffInsertTableStatement($cn, $sql, $e->getMessage(), $name))) {
                        $output->writeln(PHP_EOL.PHP_EOL.sprintf('Something weird found in `%s.%s`%sInserted %s rows, diff: %s%sSQL:%s', $cn->getDatabase(), $name, PHP_EOL.PHP_EOL, $diff['inserts'], $diff['diff'], PHP_EOL, PHP_EOL."\t".$sql.PHP_EOL.PHP_EOL."\t"));
                        ++$weird;
                        ++static::$totalWeird;
                    } elseif ($diff === false) {
                        ++$ignored;
                        ++static::$totalIgnored;
                    } else {
                        $output->writeln(PHP_EOL.PHP_EOL.sprintf('Error found in `%s.%s`%sSQL: %s%sMessage: %s%s', $cn->getDatabase(), $name, PHP_EOL, $sql, PHP_EOL.PHP_EOL, $e->getMessage(), PHP_EOL."\t"));
                        ++$errors;
                        ++static::$totalErrors;
                    }
                    continue;
                }
            }
            $output->write(sprintf('%s/%s inserts, %s/%s creates, %s/%s skips, %s/%s updates & %s/%s errors', $inserted, static::$totalInserted, $created, static::$totalCreated, $ignored, static::$totalIgnored, $weird, static::$totalWeird, $errors, static::$totalErrors).PHP_EOL);
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
