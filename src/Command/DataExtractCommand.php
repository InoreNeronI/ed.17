<?php

namespace Command;

use Doctrine\DBAL;
use Symfony\Component\Console;

class DataExtractCommand extends Console\Command\Command
{
    private static $baseBuild = '0.5';

    private static $buildColumnOptions = ['length' => 10, 'notnull' => true];

    private static $versioningTablePrefix = 'erantzunak_';

    private static $versioningTableIndexPrimary = ['build', 'code', 'id'];

    private static $ignoreTablePrefix = 'edg051_testuak_';

    private static $skipFailedStatementIfExceptionContainsAny = ['Base table or view already exists'];

    private static $statements = [];

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
        } else {
            throw new \Exception(sprintf('Error, failed opening archive `%s` (code: %s).', @$zip->getStatusString(), $zipStatus));
        }
        $structure = $extractPath.DIRECTORY_SEPARATOR.'data-structure.sql';
        $data = $extractPath.DIRECTORY_SEPARATOR.'data.sql';
        if (realpath($structure) && realpath($data)) {
            static::parseSql($structure, $data, md5_file($file), $v, $output);
        } else {
            $output->writeln(PHP_EOL.sprintf('Cannot parse files in `%s`', $extractPath));
        }
    }

    /**
     * @param string $sql
     * @return bool
     */
    private static function isCreateTableStatement($sql)
    {
        if (strpos($sql, 'CREATE TABLE `'.static::$versioningTablePrefix) !== false) {

            return true;
        }

        return false;
    }

    /**
     * @param string $sql
     * @return bool
     */
    private static function ignoreCreateTableStatement($sql)
    {
        if (strpos($sql, 'CREATE TABLE `'.static::$ignoreTablePrefix) !== false) {

            return true;
        }

        return false;
    }

    /**
     * @param string $sql
     * @return bool
     */
    private static function isInsertTableStatement($sql)
    {
        if (strpos($sql, 'INSERT INTO `'.static::$versioningTablePrefix) !== false) {

            return true;
        }

        return false;
    }

    /**
     * @param string $sql
     * @return bool
     */
    private static function ignoreInsertTableStatement($sql)
    {
        if (strpos($sql, 'INSERT INTO `'.static::$ignoreTablePrefix) !== false) {

            return true;
        }

        return false;
    }

    /**
     * @param DBAL\Connection $cn
     * @param string $sql
     * @param string $msg
     * @param string|null $table
     * @return array|false
     */
    private static function diffInsertTableStatement(DBAL\Connection $cn, $sql, $msg, $table = null)
    {
        if ($table && strpos($msg, 'Duplicate entry') !== false && preg_match('/Duplicate entry \'(.+)\' for key \'PRIMARY\'/', $msg, $matches) !== false) {
            $pk = explode('-', $matches[1], count(static::$versioningTableIndexPrimary));
            $format = 'SELECT * FROM `'.$table.'` WHERE %s = \''.implode('\' AND %s = \'', $pk).'\'';
            $result = $cn->fetchAssoc(call_user_func_array('sprintf', array_merge([$format], static::$versioningTableIndexPrimary)));
            preg_match('/VALUES \((.+)\)/', $sql, $matches);
            $newValues = array_map(function($_) { return trim($_, '\''); }, explode(',', $matches[1]));
            if (count($diff = array_diff($newValues, array_values($result))) > 0) {
                return $diff;
            }

            return false;
        }
    }

    /**
     * @param string $sql
     * @param string $msg
     * @return bool
     */
    private static function ignoreStatement($sql, $msg)
    {
        if (static::ignoreCreateTableStatement($sql) || static::ignoreInsertTableStatement($sql)) {

            return true;
        }
        foreach (static::$skipFailedStatementIfExceptionContainsAny as $ignoredSqlExceptionContain) {
            if (strpos($msg, $ignoredSqlExceptionContain) !== false) {

                return true;
            }
        }

        return false;
    }

    /**
     * @param string $structure
     * @param string $data
     * @param string $hash
     * @param string $build
     * @param Console\Output\OutputInterface $output
     */
    private static function parseSql($structure, $data, $hash, $build, Console\Output\OutputInterface $output)
    {
        static::sqlImport($structure);
        static::sqlImport($data);

        // @see https://github.com/doctrine/DoctrineBundle/blob/v1.5.2/Command/CreateDatabaseDoctrineCommand.php
        $dbTargetParams = \defDb::dbDist();
        $connTemporary = DBAL\DriverManager::getConnection($dbTargetParams);
        $dbTemporary = $dbTargetParams['dbname'] . '_' . $hash . '_' . str_replace('.', '_', $build);

        if (!in_array($dbTemporary, $connTemporary->getSchemaManager()->listDatabases())) {
            $output->writeln(PHP_EOL . sprintf('Creating temporary database `%s`...', $dbTemporary) . PHP_EOL);
            // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
            unset($dbTargetParams['dbname'], $dbTargetParams['path'], $dbTargetParams['url']);
            $connTemporary->getSchemaManager()->createDatabase($dbTemporary);
        } else {
            $output->writeln(PHP_EOL . sprintf('Using already created temporary database `%s`...', $dbTemporary) . PHP_EOL);
        }

        $dbTargetParams['dbname'] = $dbTemporary;
        static::injectStatements($dbTargetParams, $build, $output);
    }

    /**
     * @param array $dbParams
     * @param string $build
     * @param Console\Output\OutputInterface $output
     */
    private static function injectStatements($dbParams, $build, Console\Output\OutputInterface $output)
    {
        //$dbParams['wrapperClass'] = 'Doctrine\DBAL\Driver\PDOConnection';
        $connection = DBAL\DriverManager::getConnection($dbParams);
        $output->writeln(sprintf('Executing %s statements...', count(static::$statements)));
        if ($connection->getDatabasePlatform()->getName() === 'mysql') {
            $connection->executeQuery('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;');
            $sm = $connection->getSchemaManager();
            foreach (static::$statements as $sql) {
                $hasItemName = preg_match('/`(\w+)`/', $sql, $matches) !== false;
                $name = $hasItemName ? $matches[1] : null;
                if (static::isInsertTableStatement($sql) && $hasItemName) {
                    $sql = preg_replace('/^(INSERT INTO `' . static::$versioningTablePrefix . '\w+` VALUES \()/', '\\1\'' . $build . '\',', $sql, 1);
                }
                try {
                    // @see https://github.com/doctrine/dbal/blob/v2.5.12/lib/Doctrine/DBAL/Tools/Console/Command/ImportCommand.php
                    $stmt = $connection->prepare($sql);
                    $stmt->execute();
                    $output->write(' ...ok!');
                    $stmt->closeCursor();
                    if (static::isCreateTableStatement($sql) && $hasItemName) {
                        $columns = $sm->listTableColumns($name);
                        $sm->dropTable($name);
                        $buildColumn = new DBAL\Schema\Column('build', DBAL\Types\Type::getType('string'), array_merge(static::$buildColumnOptions, ['default' => $build]));
                        $pkIndex = new DBAL\Schema\Index('pk', static::$versioningTableIndexPrimary, false, true);
                        $sm->createTable(new DBAL\Schema\Table($name, array_merge([$buildColumn], $columns), [$pkIndex]));
                    }
                } catch (\Exception $e) {
                    if (static::ignoreStatement($sql, $e->getMessage())) {
                        $output->write(' ...exists');
                        continue;
                    } elseif (is_array($diff = static::diffInsertTableStatement($connection, $sql, $e->getMessage(), $name))) {
                        $output->write(' ...weird');
                        dump($diff);
                        continue;
                    } elseif (!$diff) {
                        $output->write(' ...exists');
                        continue;
                    }
                    //dump(strpos($sql, 'INSERT INTO `edg051_testuak_') === false);
                    $output->write(' ...error!');
                    dump($sql);
                    dump($connection->getDatabase());
                    dump($name);
                    throw new \RuntimeException($e);
                }
            }
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
