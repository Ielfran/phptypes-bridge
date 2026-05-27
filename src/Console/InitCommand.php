<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


final class InitCommand extends Command
{
    protected static $defaultName = 'init';

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Publish a bridge.php config file to your project root')
            ->setHelp('Run this once to create a bridge.php config file. Edit it to match your project structure.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $projectRoot = $this->detectProjectRoot();
        $target      = $projectRoot . '/bridge.php';

        if (file_exists($target)) {
            $io->warning('bridge.php already exists — not overwriting.');
            return Command::SUCCESS;
        }

        $stub = $this->getStub($projectRoot);
        file_put_contents($target, $stub);

        $io->success("Created bridge.php in {$projectRoot}");
        $io->text('Edit the file to configure your source directories and output path, then run:');
        $io->text('  <info>vendor/bin/bridge generate</info>');

        return Command::SUCCESS;
    }

    private function getStub(string $projectRoot): string
    {
        return <<<PHP
        <?php

        /**
         * PHPTypeS/bridge configuration
         *
         * @see https://github.com/PHPTypeS/bridge
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
            | 'Carbon\\Carbon'      => 'string',     // ISO date string
            | 'Brick\\Money\\Money' => 'string',     // formatted money
            |
            */
            'type_aliases' => [
                // 'Carbon\\Carbon' => 'string',
            ],
        ];

        PHP;
    }

    private function detectProjectRoot(): string
    {
        $dir = __DIR__;

        while ($dir !== '/') {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        return getcwd() ?: '.';
    }
}
