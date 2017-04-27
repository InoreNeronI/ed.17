#!/usr/bin/env php
<?php

require 'loader/autoload.php';

$app = new \Symfony\Component\Console\Application('Database extract tool');
$app->addCommands([new Command\DataExtractCommand()]);
$app->run();