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

final class ValidateCommand extends Command
{
    protected static $defaultName = 'validate';

    private readonly ConfigLoader $configLoader;

    public function __construct()
    {
        parent::__construct();
        $this->configLoader = new ConfigLoader();
    }

    protected function configure(): void
    {
        $this
            ->setName('validate')
            ->setDescription('Validate that generated TypeScript files are up to date with the PHP source')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Path to bridge.php config file')
            ->addOption('out',    'o', InputOption::VALUE_OPTIONAL, 'Output directory to validate against')
            ->setHelp(<<<'HELP'
                The <info>validate</info> command checks that the generated TypeScript files in
                your output directory match what would be generated from the current PHP source.

                Use this in CI to prevent the TypeScript client from drifting out of sync:

                  <info>vendor/bin/bridge validate</info>

                Exit codes:
                  0  — All generated files are up to date
                  1  — One or more files have drifted; run <info>generate</info> to fix
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

        if ($input->getOption('out')) {
            $config = $config->withOverrides(outputDir: $input->getOption('out'));
        }

        if ($config->sourceDirs() === []) {
            $io->error('No source directories configured.');
            return Command::FAILURE;
        }

        $io->section('Validating generated files...');

        try {
            $parser  = new ReflectionParser($config->typeAliases());
            $schema  = $parser->parse($config->sourceDirs());
        } catch (\Throwable $e) {
            $io->error('Parse error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        /** @var list<GeneratorOutput> $fresh */
        $fresh = [];

        if ($config->shouldRunGenerator('types')) {
            $fresh[] = (new TypeScriptTypeGenerator())->generate($schema);
        }
        if ($config->shouldRunGenerator('schemas')) {
            $fresh[] = (new ZodSchemaGenerator())->generate($schema);
        }
        if ($config->shouldRunGenerator('client')) {
            $fresh[] = (new FetchClientGenerator())->generate($schema);
        }

        $drifted = [];

        foreach ($fresh as $generatedOutput) {
            $onDisk = $config->outputDir() . '/' . $generatedOutput->filename();

            if (!file_exists($onDisk)) {
                $drifted[] = [
                    'file'   => $generatedOutput->filename(),
                    'reason' => 'File does not exist on disk.',
                ];
                continue;
            }

            $diskContent = file_get_contents($onDisk);

            if ($diskContent !== $generatedOutput->content()) {
                $drifted[] = [
                    'file'   => $generatedOutput->filename(),
                    'reason' => $this->describeDiff($diskContent ?: '', $generatedOutput->content()),
                ];
            }
        }

        if ($drifted === []) {
            $io->success('All generated files are up to date.');
            return Command::SUCCESS;
        }

        $io->error(sprintf(
            '%d file%s out of sync with the PHP source.',
            count($drifted),
            count($drifted) === 1 ? ' is' : 's are'
        ));

        foreach ($drifted as ['file' => $file, 'reason' => $reason]) {
            $io->text("  <fg=red>✗</> <comment>{$file}</comment>: {$reason}");
        }

        $io->newLine();
        $io->text('Run <info>vendor/bin/bridge generate</info> to regenerate and commit the updated files.');

        return Command::FAILURE;
    }

    private function describeDiff(string $disk, string $fresh): string
    {
        $diskLines  = substr_count($disk, "\n");
        $freshLines = substr_count($fresh, "\n");
        $delta      = $freshLines - $diskLines;

        if ($delta > 0) {
            return "{$delta} line(s) added";
        }

        if ($delta < 0) {
            return abs($delta) . ' line(s) removed';
        }

        return 'Content changed (same line count)';
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
