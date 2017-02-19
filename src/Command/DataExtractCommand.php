<?php

namespace App\Command;

use Doctrine\DBAL;
use Symfony\Component\Console;

class DataExtractCommand extends Console\Command\Command
{
    private static $statements = [];

    protected function configure()
    {
        $this->setName('extract-db')
            ->setDescription('Extract encrypted zip and import `data.sql` and `data-structure.sql` to database')
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

        static::sqlImport($extractPath.'/data-structure.sql');
        static::sqlImport($extractPath.'/data.sql');

        $dbTargetParams = \defDb::dbDist();
        // @see https://github.com/doctrine/DoctrineBundle/blob/v1.5.2/Command/CreateDatabaseDoctrineCommand.php
        $dbTemporary = $dbTargetParams['dbname'].'_'.uniqid();

        $output->writeln(PHP_EOL.sprintf('Creating temporary database `%s`...', $dbTemporary).PHP_EOL);
        // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
        unset($dbTargetParams['dbname'], $dbTargetParams['path'], $dbTargetParams['url']);
        $connTemporary = DBAL\DriverManager::getConnection($dbTargetParams);
        $connTemporary->getSchemaManager()->createDatabase($dbTemporary);
        $dbTargetParams['dbname'] = $dbTemporary;
        $dbTargetParams['wrapperClass'] = 'Doctrine\DBAL\PDOConnection';
        $conn = DBAL\DriverManager::getConnection($dbTargetParams);

        // @see https://github.com/doctrine/dbal/blob/v2.5.12/lib/Doctrine/DBAL/Tools/Console/Command/ImportCommand.php
        foreach ((array) static::$statements as $stmt) {
            if ($conn instanceof \Doctrine\DBAL\Driver\PDOConnection) {
                // PDO Drivers
                try {
                    $lines = 0;
                    $stmt = $conn->prepare($stmt);
                    $stmt->execute();
                    do {
                        // Required due to "MySQL has gone away!" issue
                        $stmt->fetch();
                        $stmt->closeCursor();
                        ++$lines;
                    } while ($stmt->nextRowset());
                    $output->write(sprintf('%d statements executed!', $lines).PHP_EOL);
                } catch (\PDOException $e) {
                    $output->writeln('Error!');
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
            } else {
                // Non-PDO Drivers (ie. OCI8 driver)
                $stmt = $conn->prepare($stmt);
                $rs = $stmt->execute();
                if ($rs) {
                    $output->write('OK! ');
                } else {
                    $error = $stmt->errorInfo();
                    $output->writeln('Error!');
                    throw new \RuntimeException($error[2], $error[0]);
                }
                $stmt->closeCursor();
            }
        }
    }

    /**
     * Import SQL from file
     *
     * @param string $file path to sql file
     */
    private static function sqlImport($file)
    {
        $delimiter = ';';
        $file = fopen($file, 'r');
        $isMultiLineComment = false;
        $sql = '';

        while (!feof($file)) {
            $row = fgets($file);

            // 1. ignore empty string, drops, locks and comment row
            if (trim($row) === '' || strpos($row, 'DROP TABLE') !== false || strpos($row, 'LOCK TABLE') !== false || preg_match('/^\s*(#|--\s|\/\*)/sUi', $row)) {
                continue;
            }

            // 2. clear comments
            $row = trim(static::clearSQL($row, $isMultiLineComment));

            // 3. parse delimiter row
            if (preg_match('/^DELIMITER\s+[^ ]+/sUi', $row)) {
                $delimiter = preg_replace('/^DELIMITER\s+([^ ]+)$/sUi', '$1', $row);
                continue;
            }

            // 4. separate sql queries by delimiter
            $offset = 0;
            while (strpos($row, $delimiter, $offset) !== false) {
                $delimiterOffset = strpos($row, $delimiter, $offset);
                if (static::isQuoted($delimiterOffset, $row)) {
                    $offset = $delimiterOffset + strlen($delimiter);
                } else {
                    $sql = trim($sql.' '.trim(substr($row, 0, $delimiterOffset)));
                    static::$statements[] = $sql;

                    $row = substr($row, $delimiterOffset + strlen($delimiter));
                    $offset = 0;
                    $sql = '';
                }
            }
            $sql = trim($sql.' '.$row);
        }
        if (strlen($sql) > 0) {
            static::$statements[] = $row;
        }

        fclose($file);
    }

    /**
     * Remove comments from sql
     *
     * @param string $sql
     * @param bool   $isMultiComment
     *
     * @return string
     */
    private static function clearSQL($sql, &$isMultiComment)
    {
        if ($isMultiComment) {
            if (preg_match('#\*/#sUi', $sql)) {
                $sql = preg_replace('#^.*\*/\s*#sUi', '', $sql);
                $isMultiComment = false;
            } else {
                $sql = '';
            }
            if (trim($sql) === '') {
                return $sql;
            }
        }

        $offset = 0;
        while (preg_match('{--\s|#|/\*[^!]}sUi', $sql, $matched, PREG_OFFSET_CAPTURE, $offset)) {
            list($comment, $foundOn) = $matched[0];
            if (static::isQuoted($foundOn, $sql)) {
                $offset = $foundOn + strlen($comment);
            } else {
                if (substr($comment, 0, 2) === '/*') {
                    $closedOn = strpos($sql, '*/', $foundOn);
                    if ($closedOn !== false) {
                        $sql = substr($sql, 0, $foundOn).substr($sql, $closedOn + 2);
                    } else {
                        $sql = substr($sql, 0, $foundOn);
                        $isMultiComment = true;
                    }
                } else {
                    $sql = substr($sql, 0, $foundOn);
                    break;
                }
            }
        }

        return $sql;
    }

    /**
     * Check if "offset" position is quoted
     *
     * @param int    $offset
     * @param string $text
     *
     * @return bool
     */
    private static function isQuoted($offset, $text)
    {
        if ($offset > strlen($text)) {
            $offset = strlen($text);
        }

        $isQuoted = false;
        for ($i = 0; $i < $offset; ++$i) {
            if ($text[$i] === "'") {
                $isQuoted = !$isQuoted;
            }
            if ($text[$i] === '\\' && $isQuoted) {
                ++$i;
            }
        }

        return $isQuoted;
    }
}
