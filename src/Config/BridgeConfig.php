<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Config;

use PHPTypeS\Bridge\Schema\Nodes\TypeNode;

final class BridgeConfig
{
    public function __construct(
        private readonly array $sourceDirs,
        private readonly string $outputDir,
        private readonly array $generators = ['types', 'schemas', 'client'],
        private readonly string $baseUrl = '',
        private readonly string $moduleFormat = 'esm',
        private readonly array $typeAliases = [],
        private readonly bool $dryRun = false,
        private readonly bool $quiet = false,
    ) {}

    /** @return list<string> */
    public function sourceDirs(): array
    {
        return $this->sourceDirs;
    }

    public function outputDir(): string
    {
        return $this->outputDir;
    }

    /** @return list<string> */
    public function generators(): array
    {
        return $this->generators;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function moduleFormat(): string
    {
        return $this->moduleFormat;
    }

    /** @return array<string, TypeNode> */
    public function typeAliases(): array
    {
        return $this->typeAliases;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    public function shouldRunGenerator(string $name): bool
    {
        return in_array($name, $this->generators, true);
    }

    public function withOverrides(
        ?string $outputDir = null,
        ?bool $dryRun = null,
        ?bool $quiet = null,
        ?string $baseUrl = null,
        /** @param list<string>|null $only */
        ?array $only = null,
    ): self {
        return new self(
            sourceDirs: $this->sourceDirs,
            outputDir: $outputDir ?? $this->outputDir,
            generators: $only ?? $this->generators,
            baseUrl: $baseUrl ?? $this->baseUrl,
            moduleFormat: $this->moduleFormat,
            typeAliases: $this->typeAliases,
            dryRun: $dryRun ?? $this->dryRun,
            quiet: $quiet ?? $this->quiet,
        );
    }
}
