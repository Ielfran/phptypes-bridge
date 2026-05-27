<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Generators\Output;

final class GeneratorOutput
{
    public function __construct(
        private readonly string $filename,
        private readonly string $content,
    ) {}

    public function filename(): string
    {
        return $this->filename;
    }

    public function content(): string
    {
        return $this->content;
    }

    /**
     * Convenience: write this output to the given directory.
     * Used by the CLI Writer layer.
     */
    public function writeTo(string $directory): void
    {
        $path = rtrim($directory, '/') . '/' . $this->filename;
        file_put_contents($path, $this->content);
    }
}
