#!/usr/bin/env php
<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
}
define('ROOT_DIR', dirname(__DIR__));
define('TURBO', true);
define('CONFIG_DIR', ROOT_DIR.'/app/config');

function includeIfExists($file)
{
    if (file_exists($file)) {
        return include $file;
    }
}

// find autoloader, borrowed from github.com/behat/behat
if ((!$loader = includeIfExists(ROOT_DIR.sprintf('/vendor%s/autoload.php', TURBO ? '-tiny' : '')))) {
    fwrite(STDERR,
        'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL
    );
    exit(1);
}
if ((!$functions = includeIfExists(CONFIG_DIR.'/include/functions.php'))) {
    fwrite(STDERR,
        'Missing dependencies'.PHP_EOL
    );
    exit(1);
}

$app = new \DatabaseCopy\ConsoleApplication('Database sync tool');
$app->addCommands([new \App\Command\DataInitCommand(), new \App\Command\DataSyncCommand()]);
$app->run();
