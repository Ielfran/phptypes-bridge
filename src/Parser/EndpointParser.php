<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Parser;

use ReflectionClass;
use ReflectionMethod;
use PHPTypeS\Bridge\Attributes\ApiEndpoint;
use PHPTypeS\Bridge\Attributes\ApiGroup;
use PHPTypeS\Bridge\Schema\ApiSchema;
use PHPTypeS\Bridge\Schema\EndpointSchema;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;
use PHPTypeS\Bridge\Parser\DocBlockParser;

final class EndpointParser
{
    public function __construct(
        private readonly TypeMapper $typeMapper,
        private readonly DtoParser $dtoParser,
    ) {}

    public function parse(ReflectionClass $class, ApiSchema $schema): void
    {
        $groupAttr = $this->getGroupAttribute($class);

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $endpointAttr = $this->getEndpointAttribute($method);

            if ($endpointAttr === null) {
                continue;
            }

            $endpoint = $this->buildEndpoint($method, $endpointAttr, $groupAttr, $class);
            $schema->addEndpoint($endpoint);

            $this->registerDtoFromTypeNode($endpoint->requestType(), $schema);
            $this->registerDtoFromTypeNode($endpoint->responseType(), $schema);
        }
    }

    private function buildEndpoint(
        ReflectionMethod $method,
        ApiEndpoint $attr,
        ?ApiGroup $group,
        ReflectionClass $class
    ): EndpointSchema {
        $prefix   = $group?->prefix ?? '';
        $path     = $prefix . $attr->path;
        $name     = $attr->name ?? $this->inferName($method->getName(), $attr->method);
        $tags     = array_unique(array_merge($group?->tags ?? [], $attr->tags));

        $responseType = null;
        $context      = "{$class->getName()}::{$method->getName()}()";
        $docParser    = new DocBlockParser();

        if ($attr->responseType !== null) {
            $responseType = new RefTypeNode($attr->responseType);
        } elseif ($method->getReturnType() !== null) {
            $rt = $this->typeMapper->fromReflectionType(
                $method->getReturnType(),
                $context
            );

            if ($rt instanceof \PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode) {
                $doc = $method->getDocComment() ?: '';
                if ($doc !== '') {
                    $docType = $docParser->extractReturn($doc);
                    if ($docType !== null) {
                        try {
                            $resolved = $this->resolveDocBlockType($docType, $class);
                            $rt = $this->typeMapper->fromString($resolved, $context);
                        } catch (\Throwable) {
                        }
                    }
                }
            }

            if (!($rt instanceof ScalarTypeNode && in_array($rt->phpType(), ['void', 'never', 'self', 'static'], true))) {
                $responseType = $rt;
            }
        }

        $requestType = $this->resolveRequestBodyType($method, $class);

        return new EndpointSchema(
            name: $name,
            httpMethod: strtoupper($attr->method),
            path: $path,
            controllerFqcn: $class->getName(),
            methodName: $method->getName(),
            requestType: $requestType,
            responseType: $responseType,
            pathParams: $this->extractPathParams($path),
            tags: array_values($tags),
        );
    }

    private function resolveRequestBodyType(
        ReflectionMethod $method,
        ReflectionClass $class
    ): ?\PHPTypeS\Bridge\Schema\Nodes\TypeNode {
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if ($type === null) {
                continue;
            }

            $node    = $this->typeMapper->fromReflectionType(
                $type,
                "{$class->getName()}::{$method->getName()}(\${$param->getName()})"
            );

            if ($node instanceof RefTypeNode) {
                return $node;
            }
        }

        return null;
    }

    private function extractPathParams(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return $matches[1];
    }

    private function inferName(string $methodName, string $httpMethod): string
    {
        return lcfirst($methodName);
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

    private function registerDtoFromTypeNode(
        ?\PHPTypeS\Bridge\Schema\Nodes\TypeNode $node,
        ApiSchema $schema,
        array &$visiting = [],
    ): void {
        if ($node === null) {
            return;
        }

        if ($node instanceof RefTypeNode) {
            $fqcn = $node->fqcn();

            if ($this->shouldSkipClass($fqcn)) {
                return;
            }

            if ($schema->hasDto($fqcn) || isset($visiting[$fqcn])) {
                return;
            }

            if (!class_exists($fqcn)) {
                return;
            }

            $visiting[$fqcn] = true;

            $ref = new ReflectionClass($fqcn);
            $dto = $this->dtoParser->parse($ref);
            $schema->addDto($dto);

            foreach ($dto->properties() as $prop) {
                $this->registerDtoFromTypeNode($prop->typeNode(), $schema, $visiting);
            }

            unset($visiting[$fqcn]);
            return;
        }

        if ($node instanceof \PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode) {
            $this->registerDtoFromTypeNode($node->itemType(), $schema, $visiting);
        }

        if ($node instanceof \PHPTypeS\Bridge\Schema\Nodes\UnionTypeNode) {
            foreach ($node->types() as $t) {
                $this->registerDtoFromTypeNode($t, $schema, $visiting);
            }
        }
    }

    private function getEndpointAttribute(ReflectionMethod $method): ?ApiEndpoint
    {
        $attrs = $method->getAttributes(ApiEndpoint::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    private function getGroupAttribute(ReflectionClass $class): ?ApiGroup
    {
        $attrs = $class->getAttributes(ApiGroup::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    private function resolveDocBlockType(string $docType, \ReflectionClass $class): string
    {
        $isArray = str_ends_with($docType, '[]');
        $baseType = $isArray ? substr($docType, 0, -2) : $docType;

        if (str_starts_with($baseType, '\\') || str_contains($baseType, '\\')) {
            return $docType;
        }

        if (in_array(strtolower($baseType), ['string', 'int', 'float', 'bool', 'array', 'null', 'void', 'mixed'], true)) {
            return $docType;
        }

        $file = $class->getFileName();
        if ($file === false) {
            return $docType;
        }

        $source = file_get_contents($file);
        if ($source === false) {
            return $docType;
        }

        if (preg_match('/^use\s+([^\s;{]+\\\\' . preg_quote($baseType, '/') . ')\s*;/m', $source, $m)) {
            return $isArray ? $m[1] . '[]' : $m[1];
        }

        if (preg_match('/^use\s+([^\s;{]+)\{[^}]*\b' . preg_quote($baseType, '/') . '\b[^}]*\}/m', $source, $m)) {
            return $isArray ? $m[1] . $baseType . '[]' : $m[1] . $baseType;
        }

        $controllerNamespace = $class->getNamespaceName();
        $candidate = $controllerNamespace . '\\' . $baseType;
        if (class_exists($candidate)) {
            return $isArray ? $candidate . '[]' : $candidate;
        }

        return $docType;
    }
}