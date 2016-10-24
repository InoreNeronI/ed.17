<?php

if (is_file(__DIR__.'/../vendor/autoload.php')) {
    /** @var \Composer\Autoload\ClassLoader $loader */
    $loader = require __DIR__.'/../vendor/autoload.php';
    return $loader;
} else
	throw new \Exception('Vendor files not found, please run "Composer" dependency manager: https://getcomposer.org/');