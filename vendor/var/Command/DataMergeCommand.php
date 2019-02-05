<?php

namespace Command;

use Doctrine\DBAL;
use Symfony\Component\Console;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class DataMergeCommand extends Console\Command\Command
{
    private static $databases = [];
    public static $files = [];
    public static $filesCount = 0;
    public static $filesCurrentFolder = null;
    public static $filesSourcePath = null;
    private static $ignoredExceptionMessages = ['Base table or view already exists'];
    private static $ignoredTables = ['edg051_testuak_dbh_simul', 'erantzunak_dbh_simul'];
    private static $ignoredTablePrefixes = ['05_', '10_', '20_', '30_', 'ikasleak'];

    protected function configure()
    {
        $this->setName('merge-dbs')
            ->setDescription('Merge all databases matching the given prefix')
            ->addOption('folder', 'f', Console\Input\InputOption::VALUE_REQUIRED, 'Path to the folder containing all `data.zip` files')
            ->addOption('prefix', 'p', Console\Input\InputOption::VALUE_OPTIONAL, 'Child databases prefix')
            ->addOption('reverse', 'rev', Console\Input\InputOption::VALUE_OPTIONAL, 'Start from de end', false);
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $startTime = time();
        $output->writeln(PHP_EOL.sprintf('Started at %s...', date('Y-m-d H:i:s', $startTime)));
        $path = $input->getOption('folder');
        if ('clear' === $path) {
            return static::clearDatabases($output);
        }
        if ('reset' === $path) {
            return static::resetDatabase();
        }
        if (!(static::$filesSourcePath = realpath($path))) {
            throw new \Exception(sprintf('Cannot read `%s` path', $path));
        }
        $output->writeln(PHP_EOL.sprintf('Parsing `%s` folder...', static::$filesSourcePath));
        static::parseFiles(static::$filesSourcePath, '/\.zip$/', $input->getOption('reverse'));
        $output->writeln(PHP_EOL.sprintf('...Ok! Found %s files', count(static::$files)));

        $application = new DataExtractCommand('Database extract tool');
        //$conn = static::getConnection();
        $banner = '';
        foreach (static::$files as $filePath => $uploadData) {
            static::$filesCurrentFolder = dirname($filePath);
            $label = '~~ '.sprintf('%s -> `%s`, version: %s',
                    $uploadData['title'],
                    str_replace(static::$filesSourcePath.DIRECTORY_SEPARATOR, '', $filePath),
                    $uploadData['version']).' ~~';
            $banner = '';
            for ($i = 0; $i < strlen($label); ++$i) {
                $banner .= '~';
            }
            $output->writeln(PHP_EOL.$banner.PHP_EOL.$label.PHP_EOL.$banner);
            $application->run(new Console\Input\ArrayInput(['--file' => $filePath, '--password' => getenv('ZIPS_PW'), '--version' => $uploadData['version']]), $output);
            /*$lastCreatedDb = $conn->executeQuery('SELECT DISTINCT table_schema
                                                    FROM INFORMATION_SCHEMA.TABLES
                                                    WHERE table_schema NOT IN(\'information_schema\', \'mysql\', \'performance_schema\')
                                                    ORDER BY create_time DESC LIMIT 1')->fetch()['table_schema'];
            $output->writeln(PHP_EOL.sprintf('Database `%s` created successfully', $lastCreatedDb));*/
        }
        $output->writeln(PHP_EOL.$banner.PHP_EOL.$banner);
        //$conn->close();
        $endTime = time();
        $output->writeln("\t".sprintf('Ended at %s, %s hours elapsed.', date('Y-m-d H:i:s', $endTime), round(($endTime - $startTime) / 60 / 60, 2)));
        $output->write("\t".sprintf('With %s creates, %s inserts, %s skips, %s updates and %s errors', DataExtractCommand::$totalCreated, DataExtractCommand::$totalInserted, DataExtractCommand::$totalIgnored, DataExtractCommand::$totalUpdated, DataExtractCommand::$totalErrors).PHP_EOL);
        $output->writeln($banner.PHP_EOL.$banner);
        //static::getDatabases($input->getOption('prefix'));
    }

    /**
     * @see http://stackoverflow.com/a/25258678
     *
     * @param string $folder
     * @param string $pattern
     * @param bool   $reverse
     */
    private static function parseFiles($folder, $pattern, $reverse = false)
    {
        // See: http://stackoverflow.com/a/27956187
        $dir = new RecursiveDirectoryIterator($folder, \FilesystemIterator::CURRENT_AS_FILEINFO);
        $iterator = new \RecursiveIteratorIterator($dir);
        $iterator->setFlags(\FilesystemIterator::SKIP_DOTS);
        $iterator->setFlags(\RecursiveIteratorIterator::SELF_FIRST);
        // See: http://stackoverflow.com/a/15055295
        $files = array_reverse(iterator_to_array(new \RegexIterator($iterator, $pattern, \RegexIterator::GET_MATCH)), true);
        static::$filesCount = count($files);
        $count = static::$filesCount;
        foreach ($files as $key => $file) {
            $parent = dirname($key);
            /*$uploadData = explode('+', basename($parent));*/
            $vPath = $parent.DIRECTORY_SEPARATOR.'version';
            static::$files[$key] = [
                'title' => sprintf('File %s of %s', static::$filesCount - --$count, static::$filesCount),
                'version' => trim(is_file($vPath) ? file_get_contents($vPath) ?: DataExtractCommand::$baseBuild : DataExtractCommand::$baseBuild),
                /*'when' => $uploadData[0].' '.str_replace('-', ':', $uploadData[1]),
                'where' => $uploadData[2],
                'who' => $uploadData[3],*/];
        }
    }

    /**
     * @return DBAL\Connection
     */
    private static function getConnection()
    {
        return DBAL\DriverManager::getConnection(\defDb::dbDist());
    }

    private static function resetDatabase()
    {
        $conn = static::getConnection();
        $db = $conn->getDatabase();
        $conn->close();
        $dbParams = \defDb::dbDist();
        unset($dbParams['dbname'], $dbParams['path'], $dbParams['url']);
        $conn = DBAL\DriverManager::getConnection($dbParams);
        $conn->getSchemaManager()->dropDatabase($db);
        $conn->getSchemaManager()->createDatabase($db);
        $conn->close();
    }

    /**
     * @param Console\Output\OutputInterface $output
     * @param string|null                    $prefix
     */
    private static function clearDatabases(Console\Output\OutputInterface $output, $prefix = null)
    {
        static::getDatabases($prefix);
        $total = count(static::$databases);
        if ($total > 0) {
            $output->writeln(PHP_EOL.sprintf('Purging %s databases...', $total));
            $conn = static::getConnection();
            foreach (static::$databases as $temporaryDb) {
                $conn->getSchemaManager()->dropDatabase($temporaryDb);
                $output->write(' ...`'.$temporaryDb.'`');
            }
            $conn->close();
        }
    }

    /**
     * @param string|null $prefix
     */
    private static function getDatabases($prefix = null)
    {
        $conn = static::getConnection();
        if (empty($prefix)) {
            $prefix = $conn->getDatabase();
        }

        foreach ($conn->getSchemaManager()->listDatabases() as $database) {
            if ($database !== $prefix && 0 === strpos($database, $prefix)) {
                static::$databases[] = $database;
            }
        }
        $conn->close();
    }

    /**
     * @param array  $columns
     * @param string $name
     * @param array  $extraFields
     * @param array  $extraTypes
     * @param array  $extraCommonProperties
     *
     * @return DBAL\Schema\Table
     *
     * @throws \Exception
     */
    public static function getVersionedTableObj($columns, $name, $extraFields = ['token', 'build'], $extraTypes = ['string', 'string'], $extraCommonProperties = ['length' => 40, 'notnull' => true])
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
     * @param string $name
     * @param array  $columns
     * @param array  $indexes
     * @param string $pkIndexName
     *
     * @return DBAL\Schema\Table
     */
    private static function getTableObj($name, $columns, $indexes = [], $pkIndexName = 'pk')
    {
        $pkIndex = new DBAL\Schema\Index($pkIndexName, DataExtractCommand::$versioningTablePrimaryIndex, false, true);

        return new DBAL\Schema\Table($name, $columns, array_merge([$pkIndex], $indexes));
    }

    /**
     * @param bool   $inPlace
     * @param string $defaultContainer
     *
     * @return string|false
     */
    private static function getMakeFailedSourcePath($inPlace = false, $defaultContainer = 'failed-uploads')
    {
        if (empty(self::$filesSourcePath) || !$inPlace) {
            $path = getcwd().DIRECTORY_SEPARATOR.$defaultContainer;
        } else {
            $path = self::$filesSourcePath;
        }

        return is_dir($path) || mkdir($path, 0755, true) ? $path : false;
    }

    /**
     * @see http://stackoverflow.com/a/30078367
     *
     * @param string $msg
     * @param string $errorFilename
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    public static function backupAndLog($msg, $errorFilename = 'error.log')
    {
        $target = static::backupPath(DataExtractCommand::$extractedPath, /*'failed-'.*/basename(DataExtractCommand::$extractedPath));

        return false !== $target &&
            false !== file_put_contents($path = $target.DIRECTORY_SEPARATOR.$errorFilename, $msg) ? str_replace(getcwd().DIRECTORY_SEPARATOR, '', $path) : false;
    }

    /**
     * @see http://stackoverflow.com/a/30078367
     *
     * @param string $source
     * @param string $parent
     * @param bool   $backupTarget
     * @param bool   $backupInPlace
     *
     * @return bool|string
     *
     * @throws \Exception
     */
    public static function backupPath($source, $parent, $backupTarget = false, $backupInPlace = false)
    {
        return static::xCopy($source, $target = static::getMakeFailedSourcePath($backupInPlace).DIRECTORY_SEPARATOR.$parent, $backupTarget) ? $target : false;
    }

    /**
     * Copy a file, or recursively copy a folder and its contents
     *
     * @see http://stackoverflow.com/a/12763962
     *
     * @param string $source       Source path
     * @param string $target       Destination directory-path
     * @param bool   $backupTarget Make backup if targert exists
     * @param int    $permissions  New folder creation permissions
     *
     * @return bool Returns true on success, false on failure
     */
    private static function xCopy($source, $target, $backupTarget = false, $permissions = 0755)
    {
        if (!is_dir($target)) {         // Make destination parent-directory
            mkdir($target, $permissions, true);
        } elseif (realpath($target.DIRECTORY_SEPARATOR.basename($source))) {
            $backupTarget && static::xCopy($target, $target.'+'.uniqid(), $backupTarget, $permissions);
            /*static::rrmdir($target.DIRECTORY_SEPARATOR.basename($source));*/
        }
        if (is_link($source)) {         // Check for symlinks
            return symlink(readlink($source), $target);
        } elseif (is_file($source)) {   // Simple copy for a file
            return copy($source, $target.DIRECTORY_SEPARATOR.basename($source));
        }
        static::xLoop($source, $target);

        return true;
    }

    /**
     * Loop through the folder
     *
     * @param string $source
     * @param string $target
     */
    private static function xLoop($source, $target)
    {
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            // Deep copy directories
            copy($source.DIRECTORY_SEPARATOR.$entry, $target.DIRECTORY_SEPARATOR.$entry);
        }
        // Clean up
        $dir->close();
    }

    /**
     * @see http://stackoverflow.com/a/3338133
     *
     * @param string $dir
     */
    private static function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ('.' !== $object && '..' !== $object) {
                    if (is_dir($dir.'/'.$object)) {
                        static::rrmdir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * @param array $arr
     *
     * @return mixed
     */
    public static function printArray($arr)
    {
        return str_replace('Array', '', str_replace('(', '[', str_replace(')', ']', print_r($arr, true))));
    }

    /**
     * @see https://github.com/doctrine/dbal/blob/v2.5.12/lib/Doctrine/DBAL/Tools/Console/Command/ImportCommand.php
     *
     * @param DBAL\Connection $cn
     * @param string          $sql
     *
     * @return bool
     */
    public static function runStatement(DBAL\Connection $cn = null, $sql)
    {
        $cn = $cn ?: static::getConnection();
        $stmt = $cn->prepare($sql);
        $run = $stmt->execute();
        $stmt->closeCursor();

        return $run;
    }

    /**
     * @param string      $sql
     * @param string      $msg
     * @param string|null $name
     *
     * @return bool
     */
    public static function isSkipableStatement($sql, $msg, $name = null)
    {
        if ($name && in_array($name, static::$ignoredTables)) {
            return true;
        }
        foreach (static::$ignoredTablePrefixes as $ignoredTablePrefix) {
            if (0 === strpos($sql, 'CREATE TABLE `'.$ignoredTablePrefix) || 0 === strpos($sql, 'INSERT INTO `'.$ignoredTablePrefix)) {
                return true;
            }
        }
        foreach (static::$ignoredExceptionMessages as $ignoredSqlExceptionContain) {
            if (false !== strpos($msg, $ignoredSqlExceptionContain)) {
                return true;
            }
        }

        return false;
    }
}
