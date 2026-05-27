<?php

return [
    'source_dirs' => [
        __DIR__ . '/Controllers',
        __DIR__ . '/DTOs',
    ],
    'output_dir' => __DIR__ . '/expected-output',
    'generators' => ['types', 'schemas', 'client'],
];
