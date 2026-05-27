<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use PHPTypeS\Bridge\Config\BridgeConfig;
use PHPTypeS\Bridge\Config\ConfigLoader;
use PHPTypeS\Bridge\Generators\FetchClientGenerator;
use PHPTypeS\Bridge\Generators\Output\GeneratorOutput;
use PHPTypeS\Bridge\Generators\TypeScriptTypeGenerator;
use PHPTypeS\Bridge\Generators\ZodSchemaGenerator;
use PHPTypeS\Bridge\Parser\ReflectionParser;
use PHPTypeS\Bridge\Writer\DryRunWriter;
use PHPTypeS\Bridge\Writer\FileWriter;

final class GenerateCommand extends Command
{
    protected static $defaultName = 'generate';

    private readonly ConfigLoader $configLoader;
    private readonly FileWriter $fileWriter;
    private readonly DryRunWriter $dryRunWriter;

    public function __construct()
    {
        parent::__construct();
        $this->configLoader = new ConfigLoader();
        $this->fileWriter   = new FileWriter();
        $this->dryRunWriter = new DryRunWriter();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate')
            ->setDescription('Generate TypeScript types, Zod schemas, and a fetch client from PHP API endpoints')
            ->addOption('config',   'c', InputOption::VALUE_OPTIONAL, 'Path to bridge.php config file')
            ->addOption('dir',      'd', InputOption::VALUE_OPTIONAL, 'Source directory/directories to scan (comma-separated)')
            ->addOption('out',      'o', InputOption::VALUE_OPTIONAL, 'Output directory for generated files')
            ->addOption('only',     null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of generators to run: types,schemas,client')
            ->addOption('base-url', null, InputOption::VALUE_OPTIONAL, 'Base URL for the generated fetch client')
            ->addOption('dry-run',  null, InputOption::VALUE_NONE,    'Print generated files to stdout instead of writing them')
            ->setHelp(<<<'HELP'
                The <info>generate</info> command scans your PHP controllers and DTOs and produces:

                  <comment>api.types.ts</comment>   — TypeScript interfaces for all DTOs + const enums
                  <comment>api.schemas.ts</comment>  — Zod validation schemas + inferred types
                  <comment>api.client.ts</comment>   — Typed async fetch functions, one per endpoint

                Configuration is loaded from <comment>bridge.php</comment> in your project root.
                Publish the default config with: <info>vendor/bin/bridge init</info>

                Examples:

                  <info>vendor/bin/bridge generate</info>
                  <info>vendor/bin/bridge generate --out=src/api --only=types,schemas</info>
                  <info>vendor/bin/bridge generate --dry-run</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $projectRoot = $this->detectProjectRoot();

        try {
            $config = $this->configLoader->load($projectRoot, $input->getOption('config'));
        } catch (\Throwable $e) {
            $io->error('Failed to load config: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $config = $config->withOverrides(
            outputDir: $input->getOption('out'),
            dryRun: $input->getOption('dry-run') ? true : null,
            baseUrl: $input->getOption('base-url'),
            only: $input->getOption('only')
                ? array_map('trim', explode(',', (string) $input->getOption('only')))
                : null,
        );

        if ($input->getOption('dir')) {
            $dirs   = array_map('trim', explode(',', (string) $input->getOption('dir')));
            $config = new \PHPTypeS\Bridge\Config\BridgeConfig(
                sourceDirs: $dirs,
                outputDir: $config->outputDir(),
                generators: $config->generators(),
                baseUrl: $config->baseUrl(),
                typeAliases: $config->typeAliases(),
                dryRun: $config->isDryRun(),
                quiet: $config->isQuiet(),
            );
        }

        if ($config->sourceDirs() === []) {
            $io->error(
                'No source directories configured. '
                . 'Set source_dirs in bridge.php or pass --dir=app/Http/Controllers'
            );
            return Command::FAILURE;
        }

        if (!$config->isQuiet()) {
            $io->section('Scanning PHP source files...');
            foreach ($config->sourceDirs() as $dir) {
                $io->text("  <fg=gray>→</> {$dir}");
            }
        }

        try {
            $parser = new ReflectionParser($config->typeAliases());
            $schema = $parser->parse($config->sourceDirs());
        } catch (\Throwable $e) {
            $io->error('Parse error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($schema->isEmpty()) {
            $io->warning(
                'No endpoints found. Make sure your controllers are annotated with #[ApiEndpoint].'
            );
            return Command::SUCCESS;
        }

        $endpointCount = count($schema->endpoints());
        $dtoCount      = count($schema->dtos());

        if (!$config->isQuiet()) {
            $io->text("  Found <info>{$endpointCount}</info> endpoints and <info>{$dtoCount}</info> DTOs");
        }

        /** @var list<GeneratorOutput> $outputs */
        $outputs = [];

        if ($config->shouldRunGenerator('types')) {
            $outputs[] = (new TypeScriptTypeGenerator())->generate($schema);
        }

        if ($config->shouldRunGenerator('schemas')) {
            $outputs[] = (new ZodSchemaGenerator())->generate($schema);
        }

        if ($config->shouldRunGenerator('client')) {
            $outputs[] = (new FetchClientGenerator())->generate($schema);
        }

        if ($config->isDryRun()) {
            $this->dryRunWriter->printAll($outputs);
            $io->success('Dry run complete — no files written.');
            return Command::SUCCESS;
        }

        try {
            $written = $this->fileWriter->writeAll($outputs, $config->outputDir());
        } catch (\Throwable $e) {
            $io->error('Write error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (!$config->isQuiet()) {
            $io->section('Generated files:');
            foreach ($written as $path) {
                $io->text("  <info>✓</info> {$path}");
            }
            $io->success('Done.');
        }

        return Command::SUCCESS;
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
