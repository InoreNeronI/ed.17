<?php

namespace App\Command;

use App\Command;
use DatabaseCopy\Command\BackupCommand;
use Doctrine\DBAL;
use Symfony\Component\Console;

class DataSyncCommand extends BackupCommand
{
    use Command\DataCommandTrait;

    protected $output;

    protected function configure()
    {
        $this->setName('sync')
            ->setDescription('Init for Sync from/to dist to/from local')
            ->addOption('source', 's', Console\Input\InputOption::VALUE_REQUIRED, 'source connection')
            ->addOption('target', 't', Console\Input\InputOption::VALUE_REQUIRED, 'target connection');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $this->init($input);
        $this->output = $output;
        $this->sc = DBAL\DriverManager::getConnection($this->getConfig('source'));
        $this->tc = DBAL\DriverManager::getConnection($this->getConfig('target'));
        $db = $this->sc->getDatabasePlatform()->getName() === 'mysql' ? $this->sc->getDatabase() : $this->tc->getDatabasePlatform()->getName() === 'mysql' ? $this->tc->getDatabase() : null;
        $dbExists = ($this->tc->getDatabasePlatform()->getName() === 'mysql' && $dbs = $this->tc->fetchArray("SHOW DATABASES LIKE '$db';")) && in_array($db, $dbs);

        if ($dbExists) {
            return $output->writeln(PHP_EOL.sprintf('Database `%s` already exists.', $db));
        } elseif ($this->tc->getDatabasePlatform()->getName() === 'sqlite' && $file = realpath($this->getConfig('target.path'))) {
            $path = dirname($file).DIRECTORY_SEPARATOR;
            $oldFilename = str_replace($path, '', $file);
            $newFilename = basename($oldFilename, '.db3').'#'.(new \DateTime())->format('Y-m-d#H.i.s').'.db3';
            $output->writeln(PHP_EOL.sprintf('Backing up previous database: `%s` -> `%s`', $oldFilename, $newFilename));
            rename($file, $path.$newFilename);
        }

        // make sure all connections are UTF8
        if ($this->sc->getDatabasePlatform()->getName() === 'mysql') {
            $this->sc->executeQuery('SET NAMES utf8');
        }
        $sm = $this->sc->getSchemaManager();
        if ($this->tc->getDatabasePlatform()->getName() === 'mysql') {
            $this->tc->executeQuery('SET NAMES utf8');
        }
        $tm = $this->tc->getSchemaManager();

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

        $output->writeln(PHP_EOL.sprintf('Creating database: `%s`', $this->tc->getDatabase()));
        $command = $this->getApplication()->find('init');
        $returnCode = $command->run($input, $output);

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
