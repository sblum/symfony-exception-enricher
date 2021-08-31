<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude(['vendor'])
    ->in(__DIR__)
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->files()
    ->name('*.php')
;

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'echo_tag_syntax' => false,
        'heredoc_to_nowdoc' => true,
        'increment_style' => false,
        'linebreak_after_opening_tag' => true,
        'mb_str_functions' => true,
        'multiline_whitespace_before_semicolons' => false,
        'native_function_invocation' => true,
        'no_php4_constructor' => true,
        'no_unreachable_default_argument_value' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_imports' => true,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_var_without_name' => false,
        'psr_autoloading' => true,
        'semicolon_after_instruction' => true,
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder)
;
