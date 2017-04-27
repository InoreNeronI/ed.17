<?php

namespace Command;

use Doctrine\DBAL;
use Symfony\Component\Console;

class DataMergeCommand extends Console\Command\Command
{
    private static $databases = [];

    protected function configure()
    {
        $this->setName('merge-dbs')
            ->setDescription('Merge all databases matching the given prefix')
            ->addOption('folder', 'f', Console\Input\InputOption::VALUE_REQUIRED, 'Path to the folder containing all `data.zip` files')
            ->addOption('prefix', 'p', Console\Input\InputOption::VALUE_OPTIONAL, 'Database prefix');
    }

    private static function getDatabases(Console\Input\InputInterface $input)
    {
        $connection = DBAL\DriverManager::getConnection(\defDb::dbDist());
        $prefix = $input->getOption('prefix');
        if (empty($prefix)) {
            $prefix = $connection->getDatabase();
        }

        if ($connection->getDatabasePlatform()->getName() === 'mysql') {
            $connection->executeQuery('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;');
            foreach ($connection->getSchemaManager()->listDatabases() as $database) {
                if (strlen($database) > strlen($prefix) && strpos($database, $prefix) === 0) {
                    static::$databases[] = $database;
                }
            }
        }
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        static::getDatabases($input);
        dump(static::$databases);
    }
}
