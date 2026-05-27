<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema\Nodes;

final class EnumTypeNode extends TypeNode
{
    public const KIND = 'enum';

    public function __construct(
        private readonly string $fqcn,
        private readonly string $backingType,
        private readonly array $cases,
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
        return new static($this->fqcn, $this->backingType, $this->cases, $nullable);
    }

    public function fqcn(): string
    {
        return $this->fqcn;
    }

    public function backingType(): string
    {
        return $this->backingType;
    }

    public function cases(): array
    {
        return $this->cases;
    }

    public function shortName(): string
    {
        $parts = explode('\\', $this->fqcn);
        return end($parts);
    }
}
