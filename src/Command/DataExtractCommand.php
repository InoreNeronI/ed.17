<?php

namespace App\Command;

//use Doctrine\DBAL;
use Symfony\Component\Console;

class DataExtractCommand extends Console\Command\Command
{
    protected function configure()
    {
        $this->setName('extract-db')
            ->setDescription('Init for Sync from/to dist to/from local')
            ->addOption('file', 'f', Console\Input\InputOption::VALUE_REQUIRED, 'Zip archive path')
            ->addOption('password', 'p', Console\Input\InputOption::VALUE_REQUIRED, 'Zip archive password');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $zip = new \ZipArchive();
        $file = $input->getOption('file');
        $pw = $input->getOption('password');
        $zipStatus = $zip->open($file);
        $extractPath = sys_get_temp_dir();

        if ($zipStatus === true) {
            if ($zip->setPassword($pw)) {
                if (!$zip->extractTo($extractPath)) {
                    throw new \Exception(sprintf('Error, extraction of `%s` failed (wrong `%s` password?).', $file, $pw));
                }
            }
            $zip->close();
        } else {
            throw new \Exception(sprintf('Error, failed opening archive `%s` (code: %s).', @$zip->getStatusString(), $zipStatus));
        }

        /*$connection = DBAL\DriverManager::getConnection(\defDb::dbDist());
        $connection->beginTransaction();
        foreach (\def::dbTargets() as $target) {

        }*/
    }
}
