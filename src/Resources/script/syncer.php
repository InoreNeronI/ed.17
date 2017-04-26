#!/usr/bin/env php
<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
}
if (!getenv('ROOT_DIR')) {
    putenv('ROOT_DIR='.dirname(dirname(dirname(__DIR__))));
}

// find autoloader, borrowed from github.com/behat/behat
if (!$loader = realpath(getenv('ROOT_DIR').'/vendor/autoload.php')) {
    fwrite(STDERR,
        'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL
    );
    exit(1);
}
require getenv('ROOT_DIR').'/vendor/autoload.php';
require getenv('ROOT_DIR').'/src/Resources/script/loader/functions.php';
if (!getenv('CONFIG_DIR')) {
    putenv('CONFIG_DIR='.getenv('ROOT_DIR').'/src/Resources/config');
}

$app = new \DatabaseCopy\ConsoleApplication('Database sync tool');
$app->addCommands([new Command\DataSchemaCommand(), new Command\DataSyncCommand()]);
$app->run();
