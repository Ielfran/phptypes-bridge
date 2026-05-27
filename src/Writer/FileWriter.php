<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Writer;

use PHPTypeS\Bridge\Generators\Output\GeneratorOutput;

final class FileWriter
{
   
    public function writeAll(array $outputs, string $directory): array
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        $written = [];

        foreach ($outputs as $output) {
            $path = rtrim($directory, '/') . '/' . $output->filename();
            file_put_contents($path, $output->content());
            $written[] = $path;
        }

        return $written;
    }

    public function write(GeneratorOutput $output, string $directory): string
    {
        return $this->writeAll([$output], $directory)[0];
    }
}
