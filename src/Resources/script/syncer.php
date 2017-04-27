#!/usr/bin/env php
<?php

require 'loader/autoload.php';

$app = new \DatabaseCopy\ConsoleApplication('Database sync tool');
$app->addCommands([new Command\DataSchemaCommand(), new Command\DataSyncCommand()]);
$app->run();