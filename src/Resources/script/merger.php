#!/usr/bin/env php
<?php

if ($argc !== 2) {
    throw new Exception('Please, provide path to the folder containing archives');
}

require 'loader/autoload.php';

$application = new \Command\DataMergeCommand('Database merge tool');

// see: http://symfony.com/doc/current/console/command_in_controller.html
$input = new \Symfony\Component\Console\Input\ArrayInput([
    '--folder' => $argv[1]
]);
// You can use NullOutput() if you don't need the output
$output = new \Symfony\Component\Console\Output\BufferedOutput();
$application->run($input, $output);

// return the output, don't use if you used NullOutput()
//$content = $output->fetch();