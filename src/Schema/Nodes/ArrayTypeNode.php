<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema\Nodes;

final class ArrayTypeNode extends TypeNode
{
    public const KIND = 'array';

    public function __construct(
        private readonly TypeNode $itemType,
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
        return new static($this->itemType, $nullable);
    }

    public function itemType(): TypeNode
    {
        return $this->itemType;
    }
}
