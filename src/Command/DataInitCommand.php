<?php

namespace App\Command;

use App\Command;
use DatabaseCopy\Command\CreateCommand;
use Doctrine\DBAL;
use Symfony\Component\Console;

class DataInitCommand extends CreateCommand
{
    const BATCH_SIZE = 1000;
    const MODE_COPY = 'copy';
    use Command\DataCommandTrait;

    protected function configure()
    {
        $this->setName('init')
            ->setDescription('Init for Sync from/to dist to/from local')
            ->addOption('source', 's', Console\Input\InputOption::VALUE_REQUIRED, 'source connection')
            ->addOption('target', 't', Console\Input\InputOption::VALUE_REQUIRED, 'target connection');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $this->init($input);
        $this->sc = DBAL\DriverManager::getConnection($this->getConfig('source'));
        $this->tc = DBAL\DriverManager::getConnection($this->getConfig('target'));

        // make sure all connections are UTF8
        if ($this->sc->getDatabasePlatform()->getName() === 'mysql') {
            $this->sc->executeQuery('SET NAMES utf8');
        }
        $sm = $this->sc->getSchemaManager();
        try {
            $output->writeln(PHP_EOL.'Creating target tables');
            $schema = $sm->createSchema();
            // sync configured tables only
            if (($tables = $this->getOptionalConfig('tables'))) {
                // extract target table names
                $tables = array_column($tables, 'name');
                foreach ($schema->getTables() as $table) {
                    if (!in_array($table->getName(), $tables)) {
                        $schema->dropTable($table->getName());
                    }
                }
            }
            // make sure schema is free of conflicts for target platform
            if (in_array($platform = $this->tc->getDatabasePlatform()->getName(), ['sqlite'])) {
                $this->updateSchemaForTargetPlatform($schema, $platform);
            }
            $synchronizer = new DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer($this->tc);
            $synchronizer->createSchema($schema);
        } catch (\Exception $e) {
            $sql = $schema->toSql($this->tc->getDatabasePlatform());
            echo implode($sql, PHP_EOL.PHP_EOL);
            throw $e;
        }
    }
}
