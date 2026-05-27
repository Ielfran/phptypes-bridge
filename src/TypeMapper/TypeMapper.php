<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\TypeMapper;

use ReflectionEnum;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use PHPTypeS\Bridge\Exceptions\UnsupportedTypeException;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\IntersectionTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\EnumTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\TypeNode;
use PHPTypeS\Bridge\Schema\Nodes\UnionTypeNode;

final class TypeMapper
{
    private const SCALAR_TYPES = [
        'string', 'int', 'float', 'double', 'bool',
        'boolean', 'null', 'void', 'never', 'mixed',
        'object', 'static', 'self', 'class-string',
    ];

    public function __construct(
        private readonly array $typeAliases = [],
    ) {}

    public function fromReflectionType(ReflectionType $type, string $context): TypeNode
    {
        if ($type instanceof ReflectionUnionType) {
            return $this->fromUnionType($type, $context);
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->fromNamedType($type, $context);
        }

        if ($type instanceof ReflectionIntersectionType) {
            return $this->fromIntersectionType($type, $context);
        }

        return new ScalarTypeNode('mixed');
    }

    public function fromString(string $typeString, string $context): TypeNode
    {
        $typeString = trim($typeString);

        if (str_starts_with($typeString, '?')) {
            $inner = $this->fromString(substr($typeString, 1), $context);
            return $inner->withNullable(true);
        }

        if (str_contains($typeString, '|')) {
            return $this->fromStringUnion($typeString, $context);
        }

        if (str_ends_with($typeString, '[]')) {
            $itemType = $this->fromString(substr($typeString, 0, -2), $context);
            return new ArrayTypeNode($itemType);
        }

        if (preg_match('/^(?:list|array)<([^,>]+)>$/', $typeString, $m)) {
            $itemType = $this->fromString(trim($m[1]), $context);
            return new ArrayTypeNode($itemType);
        }

        if ($typeString === 'array') {
            return new ArrayTypeNode(new ScalarTypeNode('mixed'));
        }

        if ($this->isScalar($typeString)) {
            return new ScalarTypeNode($typeString);
        }

        if (isset($this->typeAliases[$typeString])) {
            return $this->typeAliases[$typeString];
        }

        if ($this->isBackedEnum($typeString)) {
            return $this->buildEnumNode($typeString);
        }

        if (class_exists($typeString) || interface_exists($typeString)) {
            return new RefTypeNode($typeString);
        }

        throw UnsupportedTypeException::forType($typeString, $context);
    }

    private function fromNamedType(ReflectionNamedType $type, string $context): TypeNode
    {
        $name     = $type->getName();
        $nullable = $type->allowsNull();

        if (isset($this->typeAliases[$name])) {
            $node = $this->typeAliases[$name];
            return $nullable ? $node->withNullable(true) : $node;
        }

        if ($this->isScalar($name) || $type->isBuiltin()) {
            if ($name === 'array') {
                return new ArrayTypeNode(new ScalarTypeNode('mixed'), $nullable);
            }

            return new ScalarTypeNode($name, $nullable);
        }

        if ($this->isBackedEnum($name)) {
            return $this->buildEnumNode($name, $nullable);
        }

        return new RefTypeNode($name, $nullable);
    }

    private function fromUnionType(ReflectionUnionType $union, string $context): TypeNode
    {
        $nodes    = [];
        $nullable = false;

        foreach ($union->getTypes() as $type) {
            if ($type instanceof ReflectionNamedType && $type->getName() === 'null') {
                $nullable = true;
                continue;
            }

            $nodes[] = $this->fromReflectionType($type, $context);
        }

        if (count($nodes) === 1) {
            return $nodes[0]->withNullable($nullable);
        }

        return new UnionTypeNode($nodes, $nullable);
    }

    private function fromStringUnion(string $typeString, string $context): TypeNode
    {
        $parts    = array_map('trim', explode('|', $typeString));
        $nullable = false;
        $nodes    = [];

        foreach ($parts as $part) {
            if ($part === 'null') {
                $nullable = true;
                continue;
            }

            $nodes[] = $this->fromString($part, $context);
        }

        if (count($nodes) === 1) {
            return $nodes[0]->withNullable($nullable);
        }

        return new UnionTypeNode($nodes, $nullable);
    }

    private function fromIntersectionType(ReflectionIntersectionType $intersection, string $context): IntersectionTypeNode
    {
        $nodes = [];

        foreach ($intersection->getTypes() as $type) {
            $nodes[] = $this->fromReflectionType($type, $context);
        }

        return new IntersectionTypeNode($nodes);
    }

    private function isScalar(string $name): bool
    {
        return in_array(strtolower($name), self::SCALAR_TYPES, true);
    }

    private function isBackedEnum(string $fqcn): bool
    {
        if (!class_exists($fqcn)) {
            return false;
        }

        return enum_exists($fqcn);
    }

    private function buildEnumNode(string $fqcn, bool $nullable = false): EnumTypeNode
    {
        $ref         = new ReflectionEnum($fqcn);
        $backingType = $ref->getBackingType()?->getName() ?? 'string';
        $cases       = [];

        foreach ($ref->getCases() as $case) {
            $cases[$case->getName()] = $case->getBackingValue();
        }

        return new EnumTypeNode($fqcn, $backingType, $cases, $nullable);
    }
}