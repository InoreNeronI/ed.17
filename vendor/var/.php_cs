<?php
// Could be done in php.ini
ini_set('phar.readonly', 0);

$finder = PhpCsFixer\Finder::create()
    ->in([$dir = __DIR__, $dir.'/../..'])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->exclude([$dir.'/../../app/cache', $dir.'/../../build', $dir.'/../../target', $dir.'/../../vendor'])
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