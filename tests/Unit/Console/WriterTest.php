<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Generators\Output\GeneratorOutput;
use PHPTypeS\Bridge\Writer\DryRunWriter;
use PHPTypeS\Bridge\Writer\FileWriter;

final class WriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bridge_writer_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tempDir);
    }

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

    // ── FileWriter ────────────────────────────────────────────────────────────

    public function test_file_writer_creates_file_with_correct_content(): void
    {
        $output = new GeneratorOutput('api.types.ts', 'export interface Foo {}');
        $writer = new FileWriter();

        $writer->write($output, $this->tempDir);

        $this->assertFileExists($this->tempDir . '/api.types.ts');
        $this->assertSame('export interface Foo {}', file_get_contents($this->tempDir . '/api.types.ts'));
    }

    public function test_file_writer_write_all_writes_multiple_files(): void
    {
        $outputs = [
            new GeneratorOutput('api.types.ts',   'types content'),
            new GeneratorOutput('api.schemas.ts',  'schemas content'),
            new GeneratorOutput('api.client.ts',   'client content'),
        ];

        $writer  = new FileWriter();
        $written = $writer->writeAll($outputs, $this->tempDir);

        $this->assertCount(3, $written);
        $this->assertFileExists($this->tempDir . '/api.types.ts');
        $this->assertFileExists($this->tempDir . '/api.schemas.ts');
        $this->assertFileExists($this->tempDir . '/api.client.ts');
    }

    public function test_file_writer_returns_absolute_paths(): void
    {
        $output  = new GeneratorOutput('api.types.ts', 'content');
        $writer  = new FileWriter();
        $written = $writer->writeAll([$output], $this->tempDir);

        $this->assertStringStartsWith('/', $written[0]);
        $this->assertStringEndsWith('api.types.ts', $written[0]);
    }

    public function test_file_writer_creates_nested_directory_if_missing(): void
    {
        $nested = $this->tempDir . '/deeply/nested/dir';
        $output = new GeneratorOutput('api.types.ts', 'content');
        $writer = new FileWriter();

        $writer->write($output, $nested);

        $this->assertFileExists($nested . '/api.types.ts');
    }

    public function test_file_writer_overwrites_existing_file(): void
    {
        file_put_contents($this->tempDir . '/api.types.ts', 'old content');

        $output = new GeneratorOutput('api.types.ts', 'new content');
        (new FileWriter())->write($output, $this->tempDir);

        $this->assertSame('new content', file_get_contents($this->tempDir . '/api.types.ts'));
    }

    // ── DryRunWriter ──────────────────────────────────────────────────────────

    public function test_dry_run_writer_prints_filename_header(): void
    {
        $output = new GeneratorOutput('api.types.ts', 'export interface Foo {}');
        $writer = new DryRunWriter();

        ob_start();
        $writer->printAll([$output]);
        $printed = ob_get_clean();

        $this->assertStringContainsString('api.types.ts', $printed);
    }

    public function test_dry_run_writer_prints_file_content(): void
    {
        $output = new GeneratorOutput('api.types.ts', 'export interface Foo {}');
        $writer = new DryRunWriter();

        ob_start();
        $writer->printAll([$output]);
        $printed = ob_get_clean();

        $this->assertStringContainsString('export interface Foo {}', $printed);
    }

    public function test_dry_run_writer_returns_filenames(): void
    {
        $outputs = [
            new GeneratorOutput('api.types.ts',  'a'),
            new GeneratorOutput('api.schemas.ts', 'b'),
        ];

        ob_start();
        $filenames = (new DryRunWriter())->printAll($outputs);
        ob_end_clean();

        $this->assertSame(['api.types.ts', 'api.schemas.ts'], $filenames);
    }

    public function test_dry_run_writer_does_not_write_to_disk(): void
    {
        $output = new GeneratorOutput('api.types.ts', 'content');

        ob_start();
        (new DryRunWriter())->printAll([$output]);
        ob_end_clean();

        $this->assertFileDoesNotExist($this->tempDir . '/api.types.ts');
    }

    // ── GeneratorOutput::writeTo ──────────────────────────────────────────────

    public function test_generator_output_write_to_writes_file(): void
    {
        $output = new GeneratorOutput('api.types.ts', 'content');
        $output->writeTo($this->tempDir);

        $this->assertFileExists($this->tempDir . '/api.types.ts');
        $this->assertSame('content', file_get_contents($this->tempDir . '/api.types.ts'));
    }
}
