<?php

namespace Command;

use Command;
use DatabaseCopy\Command\BackupCommand;
use Symfony\Component\Console;

class DataSyncCommand extends BackupCommand
{
    use Command\DataCommandTrait;

    protected $output;

    protected function configure()
    {
        $this->setName('sync-db')
            ->setDescription('Init for Sync from/to dist to/from local')
            ->addOption('source', 's', Console\Input\InputOption::VALUE_REQUIRED, 'source connection')
            ->addOption('target', 't', Console\Input\InputOption::VALUE_REQUIRED, 'target connection');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $this->init($input);
        $this->output = $output;
        $this->prepare();
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->sc->getSchemaManager();
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $tm */
        $tm = $this->tc->getSchemaManager();
        /** @var array|null $tables */
        $tables = $this->getOptionalConfig('tables');

        if (!$this->getConfig('keep-constraints')) {
            $this->dropConstraints($tm, $tables);
        }
        if ($tables === null) {
            $output->writeln(PHP_EOL.'Tables not configured - discovering from source schema');
            $tables = [];
            foreach ($this->getTables($sm) as $tableName) {
                $output->writeln(sprintf('`%s` discovered', $tableName));
                $tables[] = ['name' => $tableName, 'mode' => static::MODE_COPY];
            }
        }

        $schemaCmd = $this->getApplication()->find('init-schema');
        $schemaCmd->run($input, $output);

        foreach ($tables as $workItem) {
            $name = $workItem['name'];
            $mode = strtolower($workItem['mode']);
            $table = $sm->listTableDetails($name);

            switch ($mode) {
                case static::MODE_SKIP:
                    $output->writeln(sprintf('`%s` skipping', $name));
                    break;

                case static::MODE_COPY:
                    $this->copyTable($table, false);
                    break;

                case static::MODE_PRIMARY_KEY:
                    $keyColumn = $this->getSimplePK($table);
                    $this->copyTable($table, $keyColumn);
                    break;

                default:
                    throw new \Exception('Unknown mode '.$mode);
            }
        }

        if (!$this->getConfig('keep-constraints')) {
            $this->addConstraints($tm);
        }
    }
}
