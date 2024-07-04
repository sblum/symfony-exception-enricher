<?php

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = PhpCsFixer\Finder::create()
    ->exclude(['node_modules', 'var', 'vendor'])
    ->in(__DIR__)
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->files()
    ->name('*.php');

$config = new PhpCsFixer\Config();

return $config
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'heredoc_to_nowdoc' => true,
        'increment_style' => false,
        'mb_str_functions' => true,
        'multiline_whitespace_before_semicolons' => true,
        'native_function_invocation' => ['include' => ['@all']],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_var_without_name' => false,
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);
