<?php

namespace App\Command;

use DatabaseCopy\Command;
use Doctrine\DBAL;
use Symfony\Component\Console;

class DataInitCommand extends Command\CreateCommand
{
    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->config['source'] = \defDb::dbDist();

        $path = str_replace('%kernel.root_dir%', ROOT_DIR.'/app', \defDb::dbLocal()['path']);
        $this->config['target'] = array_merge(\defDb::dbLocal(), ['path' => $path]);

        $this->config['tables'] = [];
        $tableNames = array_keys(isset(\defDb::dbDist()['tables']) ? \defDb::dbDist()['tables'] : array_merge(\def::dbCodes(), [\defDb::userEntity() => null]));
        foreach ($tableNames as $tableName) {
            echo sprintf('`%s` discovered', $tableName).PHP_EOL;
            $this->config['tables'][] = ['name' => $tableName, 'mode' => 'copy'];
        }
    }

    protected function configure()
    {
        $this->setName('init')->setDescription('Init for Sync from dist to local');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
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
        //$tm = $this->tc->getSchemaManager();
        try {
            $output->writeln('Creating target tables');
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
