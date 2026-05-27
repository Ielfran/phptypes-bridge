<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin'])
    ->name('*.php')
    ->notPath('vendor');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Base ruleset
        '@PSR12'                => true,
        '@PHP81Migration'       => true,
        '@PHP80Migration:risky' => true,

        // Imports
        'ordered_imports'       => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'     => true,
        'global_namespace_import' => [
            'import_classes'   => false,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Strict types
        'declare_strict_types'  => true,
        'strict_param'          => true,
        'strict_comparison'     => true,

        // Arrays
        'array_syntax'          => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'no_trailing_comma_in_singleline' => true,

        // Strings
        'single_quote'          => true,
        'no_useless_concat_operator' => true,

        // Whitespace
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'if', 'foreach', 'for', 'while', 'switch'],
        ],
        'method_chaining_indentation' => true,
        'no_extra_blank_lines'        => true,

        // Types
        'phpdoc_align'          => true,
        'phpdoc_order'          => true,
        'phpdoc_scalar'         => true,
        'phpdoc_trim'           => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_types'          => true,
        'phpdoc_var_without_name' => true,

        // Modern PHP
        'use_arrow_functions'   => true,
        'modernize_types_casting' => true,
        'no_unneeded_final_method' => true,
        'final_class'           => false, // we declare final ourselves

        // Misc
        'no_unused_lambda_captures' => true,
        'nullable_type_declaration_for_default_null_value' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
