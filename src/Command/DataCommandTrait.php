<?php

namespace App\Command;

use Symfony\Component\Console;

/**
 * Trait DataCommandTrait.
 */
trait DataCommandTrait
{
    private function fixPath(Console\Input\InputInterface $input, $origin)
    {
        $method = 'db'.ucfirst($input->getOption($origin));
        if (isset(\defDb::$method()['path'])) {
            $path = str_replace('%kernel.root_dir%', ROOT_DIR.'/app', \defDb::$method()['path']);

            return array_merge(\defDb::$method(), ['path' => $path]);
        }

        return \defDb::$method();
    }

    private function init(Console\Input\InputInterface $input, $source = 'source', $target = 'target')
    {
        $this->batch = static::BATCH_SIZE;
        $this->config[$source] = $this->fixPath($input, $source);
        $this->config[$target] = $this->fixPath($input, $target);
        $this->config['keep-constraints'] = true;
        $this->config['tables'] = [];

        $tableNames = array_keys(array_merge(\def::dbCodes(), [\defDb::userEntity() => null]));
        foreach ($tableNames as $tableName) {
            echo sprintf('`%s` discovered', $tableName).PHP_EOL;
            $this->config['tables'][] = [
                'name' => $tableName,
                'mode' => static::MODE_COPY,
            ];
        }
    }
}
