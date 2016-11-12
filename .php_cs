<?php

/** @url https://github.com/elcodi/elcodi/blob/master/.php_cs */
/** @url https://github.com/serbanghita/Mobile-Detect/blob/master/.php_cs */

ini_set('phar.readonly', 0); // Could be done in php.ini

require_once 'bin/php-cs.phar';

$config = Symfony\CS\Config\Config::create()
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
        '-unalign_double_arrow',
    ]);

$path = null === $input->getArgument('path') ? $input->getArgument('path') : __DIR__;

return $config->finder(Symfony\CS\Finder\DefaultFinder::create()
    ->in($path)
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->exclude(array('app/cache', 'bin', 'build', 'node_modules', 'vendor'))
    ->files()
    ->name('*.php'));