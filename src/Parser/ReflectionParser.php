<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Parser;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use PHPTypeS\Bridge\Attributes\ApiEndpoint;
use PHPTypeS\Bridge\Exceptions\ParserException;
use PHPTypeS\Bridge\Parser\Contracts\ParserInterface;
use PHPTypeS\Bridge\Schema\ApiSchema;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;

final class ReflectionParser implements ParserInterface
{
    private readonly TypeMapper $typeMapper;
    private readonly DtoParser $dtoParser;
    private readonly EndpointParser $endpointParser;

    public function __construct(array $typeAliases = [])
    {
        $this->typeMapper      = new TypeMapper($typeAliases);
        $this->dtoParser       = new DtoParser($this->typeMapper);
        $this->endpointParser  = new EndpointParser($this->typeMapper, $this->dtoParser);
    }

    public function parse(array $directories): ApiSchema
    {
        $schema = new ApiSchema();

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                throw new ParserException("Source directory not found: {$dir}");
            }

            $this->scanDirectory($dir, $schema);
        }

        return $schema;
    }

    private function scanDirectory(string $dir, ApiSchema $schema): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $fqcn = $this->extractClassNameFromFile($file->getRealPath());

            if ($fqcn === null) {
                continue;
            }

            $this->processClass($fqcn, $schema);
        }
    }

    private function shouldSkipClass(string $fqcn): bool
    {
        $frameworkPrefixes = [
            'Illuminate\\',
            'Symfony\\',
            'Laravel\\',
            'Doctrine\\',
            'Psr\\',
            'Carbon\\',
            'Monolog\\',
            'GuzzleHttp\\',
        ];
    
        foreach ($frameworkPrefixes as $prefix) {
            if (str_starts_with($fqcn, $prefix)) {
                return true;
            }
        }
    
        return false;
    }

    private function processClass(string $fqcn, ApiSchema $schema): void
    {
        if ($this->shouldSkipClass($fqcn)) {
            return;
        }

        if (!class_exists($fqcn) && !interface_exists($fqcn)) {
            return;
        }

        try {
            $ref = new ReflectionClass($fqcn);
        } catch (ReflectionException) {
            return;
        }

        if ($ref->isAbstract() || $ref->isInterface() || $ref->isTrait()) {
            return;
        }

        if ($this->hasEndpointMethods($ref)) {
            $this->endpointParser->parse($ref, $schema);
        }
    }

    private function extractClassNameFromFile(string $path): ?string
    {
        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        $tokens    = token_get_all($contents);
        $namespace = '';
        $className = null;

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $ns = '';
                $i++;

                while ($i < $tokenCount) {
                    $t = $tokens[$i];

                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $ns .= $t[1];
                    } elseif ($t === ';' || $t === '{') {
                        break;
                    }

                    $i++;
                }

                $namespace = $ns;
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_ENUM, T_TRAIT], true)) {
                $j = $i + 1;

                while ($j < $tokenCount) {
                    $t = $tokens[$j];

                    if (is_array($t) && $t[0] === T_STRING) {
                        $className = $t[1];
                        break;
                    }

                    if (!is_array($t) || $t[0] !== T_WHITESPACE) {
                        break;
                    }

                    $j++;
                }

                break;
            }
        }

        if ($className === null) {
            return null;
        }

        return $namespace !== '' ? "{$namespace}\\{$className}" : $className;
    }

    private function hasEndpointMethods(ReflectionClass $class): bool
    {
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(ApiEndpoint::class) !== []) {
                return true;
            }
        }

        return false;
    }
}