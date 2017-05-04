<?php

namespace Command;

use Doctrine\DBAL;
use Symfony\Component\Console;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class DataMergeCommand extends Console\Command\Command
{
    private static $baseVersion = '0.5';
    private static $databases = [];
    private static $files = [];

    protected function configure()
    {
        $this->setName('merge-dbs')
            ->setDescription('Merge all databases matching the given prefix')
            ->addOption('folder', 'f', Console\Input\InputOption::VALUE_REQUIRED, 'Path to the folder containing all `data.zip` files')
            ->addOption('prefix', 'p', Console\Input\InputOption::VALUE_OPTIONAL, 'Child databases prefix');
    }

    /**
     * @see http://stackoverflow.com/a/25258678
     *
     * @param string $folder
     * @param string $pattern
     */
    private static function parseFiles($folder, $pattern)
    {
        // See: http://stackoverflow.com/a/27956187
        $dir = new RecursiveDirectoryIterator($folder, \FilesystemIterator::CURRENT_AS_FILEINFO);
        $iterator = new \RecursiveIteratorIterator($dir);
        $iterator->setFlags(\FilesystemIterator::SKIP_DOTS);
        $iterator->setFlags(\RecursiveIteratorIterator::SELF_FIRST);
        // See: http://stackoverflow.com/a/15055295
        $files = array_reverse(iterator_to_array(new \RegexIterator($iterator, $pattern, \RegexIterator::GET_MATCH)), true);
        foreach ($files as $key => $file) {
            $parent = dirname($key);
            $vPath = $parent.DIRECTORY_SEPARATOR.'version';
            $v = is_file($vPath) ? file_get_contents($vPath) ?: static::$baseVersion : static::$baseVersion;
            $uploadParams = explode('+', basename($parent));
            static::$files[$key] = [
                'version' => trim($v),
                'when' => $uploadParams[0].' '.str_replace('-', ':', $uploadParams[1]),
                'where' => $uploadParams[2],
                'who' => $uploadParams[3],
            ];
        }
    }

    /**
     * @return DBAL\Connection
     */
    private static function getConnection()
    {
        return DBAL\DriverManager::getConnection(\defDb::dbDist());
    }

    /**
     *
     */
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
            if ($database !== $prefix && strpos($database, $prefix) === 0) {
                static::$databases[] = $database;
            }
        }
        $conn->close();
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $startTime = time();
        $output->writeln(PHP_EOL.sprintf('Started at %s...', date('Y-m-d H:i:s', $startTime)));
        $path = $input->getOption('folder');
        if ($path === 'clear') {
            return static::clearDatabases($output);
        }
        if ($path === 'reset') {
            return static::resetDatabase();
        }
        if (!$path = realpath($path)) {
            throw new \Exception(sprintf('Cannot read `%s` path', $path));
        }
        $output->writeln(PHP_EOL.sprintf('Parsing `%s` folder...', $path));
        static::parseFiles($path, '/\.zip$/');
        $output->writeln(PHP_EOL.sprintf('...Ok! Found %s files', count(static::$files)));

        $application = new DataExtractCommand('Database extract tool');
        //$conn = static::getConnection();
        $banner = '~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~';
        $legend = '~~~~~~~~~~~~~~~';
        foreach (static::$files as $filepath => $uploadParams) {
            $output->writeln(PHP_EOL.$banner);
            $output->writeln(sprintf('%s Working on `%s` folder, version: %s %s', $legend, basename(dirname($filepath)), $uploadParams['version'], $legend));
            $output->writeln($banner);
            $application->run(new Console\Input\ArrayInput(['--file' => $filepath, '--password' => getenv('ZIPS_PW'), '--version' => $uploadParams['version']]), $output);
            /*$lastCreatedDb = $conn->executeQuery('SELECT DISTINCT table_schema
                                                                            FROM INFORMATION_SCHEMA.TABLES
                                                                            WHERE table_schema NOT IN(\'information_schema\', \'mysql\', \'performance_schema\')
                                                                            ORDER BY create_time DESC LIMIT 1')->fetch()['table_schema'];
            $output->writeln(PHP_EOL.sprintf('Database `%s` created successfully', $lastCreatedDb));*/
        }
        $output->writeln(PHP_EOL.$banner);
        $output->writeln($banner);
        //$conn->close();
        $endTime = time();
        $output->writeln(sprintf('Ended at %s, %s hours elapsed.', date('Y-m-d H:i:s', $endTime), round(($endTime - $startTime) / 60 / 60, 2)));
        $output->writeln("\t".sprintf('With %s inserts, %s creates, %s skips, %s updates and %s errors', DataExtractCommand::$totalInserted, DataExtractCommand::$totalCreated, DataExtractCommand::$totalIgnored, DataExtractCommand::$totalUpdated, DataExtractCommand::$totalErrors).PHP_EOL);
        //static::getDatabases($input->getOption('prefix'));
    }
}
