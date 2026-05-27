<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema\Nodes;

final class ScalarTypeNode extends TypeNode
{
    public const KIND = 'scalar';

    public function __construct(
        private readonly string $phpType,
        private readonly bool $nullable = false,
    ) {}

    public function kind(): string
    {
        return self::KIND;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function withNullable(bool $nullable = true): static
    {
        return new static($this->phpType, $nullable);
    }

    public function phpType(): string
    {
        return $this->phpType;
    }

    public function tsType(): string
    {
        return match ($this->phpType) {
            'string', 'class-string'    => 'string',
            'int', 'float'              => 'number',
            'bool'                      => 'boolean',
            'null'                      => 'null',
            'void', 'never'             => 'void',
            'mixed', 'object', 'static' => 'unknown',
            default                     => 'unknown',
        };
    }
}
