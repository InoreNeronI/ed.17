<?php

namespace App\Command;

use Symfony\Component\Console;

/**
 * Trait DataCommandTrait.
 */
trait DataCommandTrait
{
    private function fixPath(Console\Input\InputInterface $input, $orig = 'source')
    {
        $method = 'db'.ucfirst($input->getOption($orig));
        if (isset(\defDb::$method()['path'])) {
            $path = str_replace('%kernel.root_dir%', ROOT_DIR.'/app', \defDb::$method()['path']);
            return array_merge(\defDb::$method(), ['path' => $path]);
        } else {
            return \defDb::$method();
        }
    }

    private function init(Console\Input\InputInterface $input, $source = 'source', $target = 'target')
    {
        $this->batch = static::BATCH_SIZE;
        $this->config[$source] = $this->fixPath($input, $source);
        $this->config[$target] = $this->fixPath($input, $target);
        //$this->setDefinition('keep-constraints', true);
        $this->config['keep-constraints'] = true;

        //isset(\defDb::dbDist()['tables']) ? $input->setOption('tables', \defDb::dbDist()['tables']) : null;
        $this->config['tables'] = [];
        $tableNames = array_keys(/*isset(\defDb::dbDist()['tables']) ? \defDb::dbDist()['tables'] : */array_merge(\def::dbCodes(), [\defDb::userEntity() => null]));
        foreach ($tableNames as $tableName) {
            echo sprintf('`%s` discovered', $tableName).PHP_EOL;
            $this->config['tables'][] = [
                'name' => $tableName,
                'mode' => static::MODE_COPY,
            ];
        }
    }
}
