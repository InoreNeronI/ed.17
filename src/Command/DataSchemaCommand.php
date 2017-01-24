<?php

namespace App\Command;

use App\Command;
use DatabaseCopy\Command\CreateCommand;
use Doctrine\DBAL;
use Symfony\Component\Console;

class DataSchemaCommand extends CreateCommand
{
    const MODE_COPY = 'copy';

    use Command\DataCommandTrait;

    protected function configure()
    {
        $this->setName('init-schema')
            ->setDescription('Init for Sync from/to dist to/from local')
            ->addOption('source', 's', Console\Input\InputOption::VALUE_REQUIRED, 'source connection')
            ->addOption('target', 't', Console\Input\InputOption::VALUE_REQUIRED, 'target connection');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $this->init($input);
        $output->writeln(PHP_EOL.'Creating target tables...');
        /** @var DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->sc->getSchemaManager();
        /** @var DBAL\Schema\Schema $schema */
        $schema = $sm->createSchema();
        try {
            // sync configured tables only
            if ($tables = $this->getOptionalConfig('tables')) {
                // extract target table names
                $tables = array_column($tables, 'name');
                foreach ($schema->getTables() as $table) {
                    if (!in_array($table->getName(), $tables)) {
                        $schema->dropTable($table->getName());
                    }
                }
            }
            // make sure schema is free of conflicts for target platform
            $this->updateSchemaForTargetPlatform($schema, $this->tc->getDatabasePlatform()->getName());
            $synchronizer = new DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer($this->tc);
            $synchronizer->createSchema($schema);
        } catch (\Exception $e) {
            $sql = $schema->toSql($this->tc->getDatabasePlatform());
            echo implode($sql, PHP_EOL.PHP_EOL);
            throw $e;
        }
    }

    /**
     * @param DBAL\Schema\Table $table
     * @param string            $collation
     */
    protected function addDefaultCollation(DBAL\Schema\Table $table, $collation = 'utf8mb4_unicode_ci')
    {
        /** @var DBAL\Schema\Column $column */
        foreach ($table->getColumns() as $column) {
            $options = $column->getPlatformOptions();
            if (!$column->hasPlatformOption('collation') || $options['collation'] === 'BINARY') {
                $options['collation'] = $collation;
                $column->setPlatformOptions($options);
            }
        }
    }

    /**
     * @param DBAL\Schema\Schema $schema
     * @param string             $platform
     */
    protected function updateSchemaForTargetPlatform(/*DBAL\Schema\Schema */$schema, $platform)
    {
        echo PHP_EOL.sprintf('Updating schema assets for target platform compatibility: %s', $platform).PHP_EOL;

        foreach ($schema->getTables() as $table) {
            echo PHP_EOL.sprintf('table: %s', $table->getName());

            switch ($platform) {
                case 'sqlite':
                    $this->indexes = [];
                    $this->renameIndexes($table);
                    $this->removeCollations($table);
                    break;
                default:
                    $this->addDefaultCollation($table);
            }
        }
        echo PHP_EOL;
    }
}
