<?php

/** @url https://github.com/elcodi/elcodi/blob/master/.php_cs */
/** @url https://github.com/serbanghita/Mobile-Detect/blob/master/.php_cs */
/** @url https://github.com/javiereguiluz/EasyAdminBundle/blob/232a193deed4cd490bc30b1584e97f05e3d8441a/.php_cs */

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
        '-phpdoc_to_comment',
        '-phpdoc_short_description',
        '-phpdoc_var_without_name',
        '-unalign_double_arrow',
    ]);

$path = is_null($input->getArgument('path')) ? __DIR__ : $input->getArgument('path');

return $config->finder(Symfony\CS\Finder\DefaultFinder::create()
    ->in($path)
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->exclude(['app/cache', 'build', 'vendor'])
    ->files()
    ->name('*.php'));