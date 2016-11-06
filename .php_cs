<?php

/** @url https://github.com/elcodi/elcodi/blob/master/.php_cs */

ini_set('phar.readonly', 0); // Could be done in php.ini

require_once 'bin/fixer-php';

$config = Symfony\CS\Config::create()
    // use SYMFONY_LEVEL:
    ->level(Symfony\CS\FixerInterface::SYMFONY_LEVEL)
    // and extra fixers:
    ->fixers([
        'concat_with_spaces',
        'multiline_spaces_before_semicolon',
        'short_array_syntax',
        '-remove_lines_between_uses',
        '-empty_return',
        '-phpdoc_var_without_name',
        '-phpdoc_to_comment',
    ]);

if (null === $input->getArgument('path')) {
    $config
        ->finder(
	        Symfony\CS\Finder\DefaultFinder::create()->in('src/')
        );
}

return $config;