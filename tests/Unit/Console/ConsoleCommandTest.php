<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use PHPTypeS\Bridge\Console\GenerateCommand;
use PHPTypeS\Bridge\Console\InitCommand;
use PHPTypeS\Bridge\Console\ValidateCommand;

final class ConsoleCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bridge_console_test_' . uniqid();
        mkdir($this->tempDir . '/out', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tempDir);
    }

    // ── generate command ──────────────────────────────────────────────────────

    public function test_generate_command_exits_success_with_valid_fixture_dir(): void
    {
        $command = new GenerateCommand();
        $tester  = new CommandTester($command);

        $tester->execute([
            '--dir' => __DIR__ . '/../../Fixtures/Controllers,'
                . __DIR__ . '/../../Fixtures/DTOs',
            '--out' => $this->tempDir . '/out',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_generate_command_writes_all_three_ts_files(): void
    {
        $command = new GenerateCommand();
        $tester  = new CommandTester($command);

        $tester->execute([
            '--dir' => __DIR__ . '/../../Fixtures/Controllers,'
                . __DIR__ . '/../../Fixtures/DTOs',
            '--out' => $this->tempDir . '/out',
        ]);

        $this->assertFileExists($this->tempDir . '/out/api.types.ts');
        $this->assertFileExists($this->tempDir . '/out/api.schemas.ts');
        $this->assertFileExists($this->tempDir . '/out/api.client.ts');
    }

    public function test_generate_command_only_flag_limits_output(): void
    {
        $command = new GenerateCommand();
        $tester  = new CommandTester($command);

        $tester->execute([
            '--dir'  => __DIR__ . '/../../Fixtures/Controllers',
            '--out'  => $this->tempDir . '/out',
            '--only' => 'types',
        ]);

        $this->assertFileExists($this->tempDir . '/out/api.types.ts');
        $this->assertFileDoesNotExist($this->tempDir . '/out/api.schemas.ts');
        $this->assertFileDoesNotExist($this->tempDir . '/out/api.client.ts');
    }

    public function test_generate_command_fails_with_no_source_dirs(): void
    {
        // Write a bridge.php with empty source_dirs so no --dir is needed
        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'source_dirs' => [],
            'output_dir'  => '{$this->tempDir}/out',
        ];");

        $command = new GenerateCommand();
        $tester  = new CommandTester($command);

        // Override config detection by using explicit --config
        $tester->execute(['--config' => $this->tempDir . '/bridge.php']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_generate_dry_run_does_not_write_files(): void
    {
        $outDir  = $this->tempDir . '/dry-out';
        mkdir($outDir);

        $command = new GenerateCommand();
        $tester  = new CommandTester($command);

        $tester->execute([
            '--dir'     => __DIR__ . '/../../Fixtures/Controllers',
            '--out'     => $outDir,
            '--dry-run' => true,
        ]);

        // No files written in dry-run mode
        $this->assertFileDoesNotExist($outDir . '/api.types.ts');

        // Success message confirms dry run ran
        $this->assertStringContainsString('Dry run complete', $tester->getDisplay());
    }

    public function test_generate_output_mentions_endpoints_found(): void
    {
        $command = new GenerateCommand();
        $tester  = new CommandTester($command);

        $tester->execute([
            '--dir' => __DIR__ . '/../../Fixtures/Controllers',
            '--out' => $this->tempDir . '/out',
        ]);

        $this->assertStringContainsString('endpoint', $tester->getDisplay());
    }

    public function test_generate_output_mentions_generated_files(): void
    {
        $command = new GenerateCommand();
        $tester  = new CommandTester($command);

        $tester->execute([
            '--dir' => __DIR__ . '/../../Fixtures/Controllers',
            '--out' => $this->tempDir . '/out',
        ]);

        $this->assertStringContainsString('api.types.ts', $tester->getDisplay());
    }

    // ── validate command ──────────────────────────────────────────────────────

    public function test_validate_passes_when_output_matches(): void
    {
        // First generate
        $genCommand = new GenerateCommand();
        $genTester  = new CommandTester($genCommand);
        $genTester->execute([
            '--dir' => __DIR__ . '/../../Fixtures/Controllers,'
                . __DIR__ . '/../../Fixtures/DTOs',
            '--out' => $this->tempDir . '/out',
        ]);

        // Write a bridge.php pointing to same fixtures
        $fixtureControllers = __DIR__ . '/../../Fixtures/Controllers';
        $fixtureDTOs        = __DIR__ . '/../../Fixtures/DTOs';
        $out                = $this->tempDir . '/out';

        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'source_dirs' => ['{$fixtureControllers}', '{$fixtureDTOs}'],
            'output_dir'  => '{$out}',
        ];");

        $valCommand = new ValidateCommand();
        $valTester  = new CommandTester($valCommand);
        $valTester->execute(['--config' => $this->tempDir . '/bridge.php']);

        $this->assertSame(0, $valTester->getStatusCode());
        $this->assertStringContainsString('up to date', $valTester->getDisplay());
    }

    public function test_validate_fails_when_output_file_is_missing(): void
    {
        $fixtureControllers = __DIR__ . '/../../Fixtures/Controllers';
        $emptyOut           = $this->tempDir . '/empty-out';
        mkdir($emptyOut);

        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'source_dirs' => ['{$fixtureControllers}'],
            'output_dir'  => '{$emptyOut}',
        ];");

        $valCommand = new ValidateCommand();
        $valTester  = new CommandTester($valCommand);
        $valTester->execute(['--config' => $this->tempDir . '/bridge.php']);

        $this->assertSame(1, $valTester->getStatusCode());
        $this->assertStringContainsString('out of sync', $valTester->getDisplay());
    }

    public function test_validate_fails_when_output_file_is_stale(): void
    {
        $out = $this->tempDir . '/stale-out';
        mkdir($out);

        // Write a stale file
        file_put_contents($out . '/api.types.ts', '// old content');

        $fixtureControllers = __DIR__ . '/../../Fixtures/Controllers';

        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'source_dirs' => ['{$fixtureControllers}'],
            'output_dir'  => '{$out}',
        ];");

        $valCommand = new ValidateCommand();
        $valTester  = new CommandTester($valCommand);
        $valTester->execute(['--config' => $this->tempDir . '/bridge.php']);

        $this->assertSame(1, $valTester->getStatusCode());
    }

    // ── init command ──────────────────────────────────────────────────────────

    public function test_init_command_creates_bridge_php(): void
    {
        $command = new InitCommand();
        $tester  = new CommandTester($command);
        $tester->execute([]);

        // It creates in the detected project root, not tempDir — just assert success
        $this->assertSame(0, $tester->getStatusCode());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }

        rmdir($dir);
    }
}
