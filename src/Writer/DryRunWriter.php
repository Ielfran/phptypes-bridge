<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Writer;

use PHPTypeS\Bridge\Generators\Output\GeneratorOutput;


final class DryRunWriter
{
    public function printAll(array $outputs): array
    {
        $filenames = [];

        foreach ($outputs as $output) {
            echo str_repeat('─', 60) . "\n";
            echo "// {$output->filename()}\n";
            echo str_repeat('─', 60) . "\n";
            echo $output->content();
            echo "\n";
            $filenames[] = $output->filename();
        }

        return $filenames;
    }
}
