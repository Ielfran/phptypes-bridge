<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Config\ConfigLoader;
use PHPTypeS\Bridge\Exceptions\ParserException;

final class ConfigLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bridge_config_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        foreach (glob($this->tempDir . '/*') ?: [] as $f) { if (is_dir($f)) { rmdir($f); continue; }
            unlink($f);
        }
        @rmdir($this->tempDir);
    }

    // ── Zero-config defaults ──────────────────────────────────────────────────

    public function test_returns_default_config_when_no_files_exist(): void
    {
        $config = (new ConfigLoader())->load($this->tempDir);

        $this->assertSame(['types', 'schemas', 'client'], $config->generators());
        $this->assertSame('esm', $config->moduleFormat());
        $this->assertFalse($config->isDryRun());
        $this->assertFalse($config->isQuiet());
    }

    public function test_default_output_dir_resolves_to_absolute_path(): void
    {
        $config = (new ConfigLoader())->load($this->tempDir);

        $this->assertStringStartsWith('/', $config->outputDir());
        $this->assertStringContainsString('generated', $config->outputDir());
    }

    // ── bridge.php loading ────────────────────────────────────────────────────

    public function test_loads_source_dirs_from_bridge_php(): void
    {
        $srcDir = $this->tempDir . '/app';
        mkdir($srcDir);

        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'source_dirs' => ['{$srcDir}'],
            'output_dir'  => '{$this->tempDir}/out',
        ];");

        $config = (new ConfigLoader())->load($this->tempDir);

        $this->assertContains($srcDir, $config->sourceDirs());
    }

    public function test_loads_output_dir_from_bridge_php(): void
    {
        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'source_dirs' => [],
            'output_dir'  => '{$this->tempDir}/generated',
        ];");

        $config = (new ConfigLoader())->load($this->tempDir);

        $this->assertSame($this->tempDir . '/generated', $config->outputDir());
    }

    public function test_generators_key_is_respected(): void
    {
        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'generators' => ['types', 'schemas'],
        ];");

        $config = (new ConfigLoader())->load($this->tempDir);

        $this->assertSame(['types', 'schemas'], $config->generators());
        $this->assertFalse($config->shouldRunGenerator('client'));
    }

    public function test_generator_aliases_are_normalised(): void
    {
        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'generators' => ['typescript', 'zod', 'fetch'],
        ];");

        $config = (new ConfigLoader())->load($this->tempDir);

        $this->assertSame(['types', 'schemas', 'client'], $config->generators());
    }

    public function test_base_url_is_loaded(): void
    {
        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'base_url' => 'https://api.example.com',
        ];");

        $config = (new ConfigLoader())->load($this->tempDir);

        $this->assertSame('https://api.example.com', $config->baseUrl());
    }

    // ── Explicit config file path ─────────────────────────────────────────────

    public function test_explicit_config_file_is_used_when_given(): void
    {
        $customConfig = $this->tempDir . '/custom-bridge.php';
        file_put_contents($customConfig, "<?php return [
            'output_dir' => '{$this->tempDir}/custom-out',
        ];");

        $config = (new ConfigLoader())->load($this->tempDir, $customConfig);

        $this->assertSame($this->tempDir . '/custom-out', $config->outputDir());
    }

    public function test_throws_when_explicit_config_file_not_found(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        (new ConfigLoader())->load($this->tempDir, $this->tempDir . '/does-not-exist.php');
    }

    public function test_throws_when_config_file_does_not_return_array(): void
    {
        $badConfig = $this->tempDir . '/bad-bridge.php';
        file_put_contents($badConfig, "<?php return 'not an array';");

        $this->expectException(ParserException::class);
        $this->expectExceptionMessageMatches('/must return an array/i');

        (new ConfigLoader())->load($this->tempDir, $badConfig);
    }

    // ── Relative path resolution ──────────────────────────────────────────────

    public function test_relative_output_dir_resolved_against_project_root(): void
    {
        file_put_contents($this->tempDir . '/bridge.php', "<?php return [
            'output_dir' => 'resources/js/api',
        ];");

        $config = (new ConfigLoader())->load($this->tempDir);

        $this->assertSame($this->tempDir . '/resources/js/api', $config->outputDir());
    }

    // ── withOverrides ─────────────────────────────────────────────────────────

    public function test_with_overrides_replaces_output_dir(): void
    {
        $config    = (new ConfigLoader())->makeMinimal(['/src'], '/out');
        $overridden = $config->withOverrides(outputDir: '/new-out');

        $this->assertSame('/new-out', $overridden->outputDir());
        $this->assertSame(['/src'], $overridden->sourceDirs());
    }

    public function test_with_overrides_sets_dry_run(): void
    {
        $config    = (new ConfigLoader())->makeMinimal(['/src'], '/out');
        $overridden = $config->withOverrides(dryRun: true);

        $this->assertTrue($overridden->isDryRun());
    }

    public function test_with_overrides_filters_generators(): void
    {
        $config    = (new ConfigLoader())->makeMinimal(['/src'], '/out');
        $overridden = $config->withOverrides(only: ['types']);

        $this->assertTrue($overridden->shouldRunGenerator('types'));
        $this->assertFalse($overridden->shouldRunGenerator('schemas'));
        $this->assertFalse($overridden->shouldRunGenerator('client'));
    }

    // ── makeMinimal ───────────────────────────────────────────────────────────

    public function test_make_minimal_sets_source_dirs_and_output(): void
    {
        $config = (new ConfigLoader())->makeMinimal(['/app/controllers'], '/out/api');

        $this->assertSame(['/app/controllers'], $config->sourceDirs());
        $this->assertSame('/out/api', $config->outputDir());
        $this->assertSame(['types', 'schemas', 'client'], $config->generators());
    }
}
