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

        // make sure all connections are UTF8
        if ($this->sc->getDatabasePlatform()->getName() === 'mysql') {
            $this->sc->executeQuery('SET NAMES utf8'); /*
        }
        if ($this->tc->getDatabasePlatform()->getName() == 'mysql') {
            $this->tc->executeQuery("SET NAMES utf8");*/
        }
        $sm = $this->sc->getSchemaManager();
        $tm = $this->tc->getSchemaManager();

        if (!$this->getConfig('keep-constraints')) {
            $this->dropConstraints($tm, $this->getConfig('tables'));
        }

        if (($tables = $this->getOptionalConfig('tables')) === null) {
            $output->writeln('Tables not configured - discovering from source schema');
            $tables = [];
            foreach ($this->getTables($sm) as $tableName) {
                $output->writeln(sprintf('`%s` discovered', $tableName));
                $tables[] = ['name' => $tableName, 'mode' => static::MODE_COPY];
            }
        }
        try {
            $db = $this->getConfig('target.dbname');
            $dbExists = in_array($db, $tm->listDatabases());

            if (!$dbExists) {
                throw new \WarningException(sprintf('Database `%s` doesn\'t exist.', $db), 404);
            }
        } catch (\Exception $e) {
            $output->writeln(PHP_EOL.$e->getMessage().PHP_EOL);
        }
        if ($file = realpath($this->getConfig('target.path'))) {
            $path = dirname($file).DIRECTORY_SEPARATOR;
            $oldFilename = str_replace($path, '', $file);
            $newFilename = basename($oldFilename, '.db3').'#'.(new \DateTime())->format('Y-m-d#H.i.s').'.db3';
            $output->writeln(sprintf('Backing up previous database: `%s` -> `%s`'.PHP_EOL, $oldFilename, $newFilename));
            rename($file, $path.$newFilename);
        }
        $command = $this->getApplication()->find('init');
        $returnCode = $command->run($input, $output);
        foreach ($tables as $workItem) {
            $name = $workItem['name'];
            //var_dump($this->validateTableExists($sm, $name));exit;
            //$this->validateTableExists($sm, $name);
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
