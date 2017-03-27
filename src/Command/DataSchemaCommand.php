<?php

namespace Command;

use Command;
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
        if ($this->sc->getDatabasePlatform()->getName() === 'sqlite') {
            $dbName = basename($this->sc->getDatabase(), '.'.pathinfo($this->sc->getDatabase(), PATHINFO_EXTENSION));
        } else {
            $dbName = $this->sc->getDatabase();
        }
        $output->writeln('Creating target tables...');
        /** @var DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->sc->getSchemaManager();
        /** @var DBAL\Schema\Schema $schema */
        $schema = $sm->createSchema();
        try {
            // sync configured tables only
            /** @var array $tables */
            $tables = array_column($this->getOptionalConfig('tables')[$dbName], 'name');
            // extract target table names
            foreach ($schema->getTables() as $table) {
                if (!in_array($table->getName(), $tables)) {
                    $schema->dropTable($table->getName());
                }
            }
            // make sure schema is free of conflicts for target platform
            $this->updateSchemaForTargetPlatform($schema, $this->tc->getDatabasePlatform()->getName());
            $synchronizer = new DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer($this->tc);
            $synchronizer->createSchema($schema);
        } catch (\Exception $e) {
            echo ''.PHP_EOL;
            echo implode($schema->toSql($this->tc->getDatabasePlatform()), PHP_EOL.PHP_EOL);
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
