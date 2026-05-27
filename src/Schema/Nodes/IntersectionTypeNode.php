<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema\Nodes;

use PHPTypeS\Bridge\Exceptions\ParserException;

final class IntersectionTypeNode extends TypeNode
{
    public const KIND = 'intersection';

    private readonly array $types;

    public function __construct(array $types, private readonly bool $nullable = false)
    {
        if (count($types) < 2) {
            throw new ParserException(
                'IntersectionTypeNode requires at least 2 types.'
            );
        }

        $this->types = array_values($types);
    }

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
        return new static($this->types, $nullable);
    }

    public function types(): array
    {
        return $this->types;
    }
}
