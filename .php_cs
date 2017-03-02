<?php

/** @url https://github.com/elcodi/elcodi/blob/master/.php_cs */
/** @url https://github.com/serbanghita/Mobile-Detect/blob/master/.php_cs */
/** @url https://github.com/javiereguiluz/EasyAdminBundle/blob/232a193deed4cd490bc30b1584e97f05e3d8441a/.php_cs */
/** @url https://github.com/javiereguiluz/EasyAdminBundle/blob/4b4613ed6e22250c7db158505529de709682278b/.php_cs */

ini_set('phar.readonly', 0); // Could be done in php.ini

require_once 'bin/php-cs.phar';

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->exclude(['app', 'build', 'vendor', 'vendor-tiny'])
    ->files()
    ->name('*.php');

return PhpCsFixer\Config::create()
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => [
            'align_double_arrow' => false,
        ],
        'combine_consecutive_unsets' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_imports' => true,
        'php_unit_strict' => true,
        'phpdoc_summary' => false,
        'strict_comparison' => true,
    ]);