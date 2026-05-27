<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Config;

use PHPTypeS\Bridge\Exceptions\ParserException;

final class ConfigLoader
{
    private const DEFAULTS = [
        'source_dirs' => [],
        'output_dir'  => 'generated',
        'generators'  => ['types', 'schemas', 'client'],
        'base_url'    => '',
        'module_format' => 'esm',
        'type_aliases'  => [],
    ];

   
    public function load(string $projectRoot, ?string $configFile = null): BridgeConfig
    {
        $raw = $this->loadRaw($projectRoot, $configFile);
        return $this->buildConfig($raw, $projectRoot);
    }

    
    public function makeMinimal(array $sourceDirs, string $outputDir): BridgeConfig
    {
        return new BridgeConfig(
            sourceDirs: $sourceDirs,
            outputDir: $outputDir,
        );
    }

    private function loadRaw(string $projectRoot, ?string $configFile): array
    {
        if ($configFile !== null) {
            if (!file_exists($configFile)) {
                throw new ParserException("Config file not found: {$configFile}");
            }
            return array_merge(self::DEFAULTS, $this->requirePhpFile($configFile));
        }

        $discovered = $projectRoot . '/bridge.php';
        if (file_exists($discovered)) {
            return array_merge(self::DEFAULTS, $this->requirePhpFile($discovered));
        }

        $composerJson = $projectRoot . '/composer.json';
        if (file_exists($composerJson)) {
            $composer = json_decode((string) file_get_contents($composerJson), true);
            if (is_array($composer) && isset($composer['extra']['bridge'])) {
                return array_merge(self::DEFAULTS, $composer['extra']['bridge']);
            }
        }

        return self::DEFAULTS;
    }

    private function requirePhpFile(string $path): array
    {
        $result = require $path;

        if (!is_array($result)) {
            throw new ParserException(
                "Config file {$path} must return an array, got " . gettype($result)
            );
        }

        return $result;
    }

    
    private function buildConfig(array $raw, string $projectRoot): BridgeConfig
    {
        $sourceDirs = $this->resolvePaths((array) ($raw['source_dirs'] ?? []), $projectRoot);
        $outputDir  = $this->resolvePath((string) ($raw['output_dir'] ?? 'generated'), $projectRoot);

        $generators = $this->normaliseGenerators((array) ($raw['generators'] ?? ['types', 'schemas', 'client']));

        $typeAliases = $this->buildTypeAliases((array) ($raw['type_aliases'] ?? []));

        return new BridgeConfig(
            sourceDirs: $sourceDirs,
            outputDir: $outputDir,
            generators: $generators,
            baseUrl: (string) ($raw['base_url'] ?? ''),
            moduleFormat: in_array($raw['module_format'] ?? 'esm', ['esm', 'cjs'], true)
                ? (string) $raw['module_format']
                : 'esm',
            typeAliases: $typeAliases,
        );
    }

   
    private function resolvePaths(array $paths, string $root): array
    {
        return array_values(array_map(
            fn(string $p) => $this->resolvePath($p, $root),
            $paths
        ));
    }

    private function resolvePath(string $path, string $root): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }

  
    private function normaliseGenerators(array $raw): array
    {
        $aliasMap = [
            'ts'         => 'types',
            'typescript' => 'types',
            'zod'        => 'schemas',
            'schema'     => 'schemas',
            'fetch'      => 'client',
            'axios'      => 'client',
        ];

        $valid   = ['types', 'schemas', 'client'];
        $result  = [];

        foreach ($raw as $name) {
            $name = strtolower((string) $name);
            $name = $aliasMap[$name] ?? $name;

            if (in_array($name, $valid, true) && !in_array($name, $result, true)) {
                $result[] = $name;
            }
        }

        return $result ?: ['types', 'schemas', 'client'];
    }

   
    private function buildTypeAliases(array $raw): array
    {
        $aliases  = [];
        $mapper   = new \PHPTypeS\Bridge\TypeMapper\TypeMapper();

        foreach ($raw as $phpClass => $tsType) {
            try {
                $aliases[(string) $phpClass] = $mapper->fromString((string) $tsType, 'type_aliases config');
            } catch (\Throwable) {
                // Unknown alias target — skip; will be caught at parse time if used
            }
        }

        return $aliases;
    }
}
