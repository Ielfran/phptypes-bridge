<?php

/**
 * typedphp/bridge configuration
 *
 * @see https://github.com/ielfran/phptypes-bridge
 */
return [
    /*
    |--------------------------------------------------------------
    | Source directories
    |--------------------------------------------------------------
    | Directories to scan for PHP controllers and DTOs.
    | Relative paths are resolved from this file's directory.
    |
    */
    'source_dirs' => [
        __DIR__ . '/app/Http/Controllers',
        __DIR__ . '/app/DTOs',
    ],

    /*
    |--------------------------------------------------------------
    | Output directory
    |--------------------------------------------------------------
    | Where to write the generated TypeScript files.
    |
    */
    'output_dir' => __DIR__ . '/resources/js/api',

    /*
    |--------------------------------------------------------------
    | Generators
    |--------------------------------------------------------------
    | Which files to generate. Remove any you don't need.
    |   'types'   → api.types.ts   (TypeScript interfaces)
    |   'schemas' → api.schemas.ts (Zod validators)
    |   'client'  → api.client.ts  (Typed fetch functions)
    |
    */
    'generators' => ['types', 'schemas', 'client'],

    /*
    |--------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------
    | The base URL injected into the generated fetch client.
    | Leave empty to use a relative path.
    |
    */
    'base_url' => '',

    /*
    |--------------------------------------------------------------
    | Type aliases
    |--------------------------------------------------------------
    | Map PHP class FQCNs to TypeScript type strings for types
    | that can't be inferred automatically (e.g. value objects).
    |
    | 'Carbon\Carbon'      => 'string',     // ISO date string
    | 'Brick\Money\Money' => 'string',     // formatted money
    |
    */
    'type_aliases' => [
        // 'Carbon\Carbon' => 'string',
    ],
];
