<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema\Nodes;

final class RefTypeNode extends TypeNode
{
    public const KIND = 'ref';

    public function __construct(
        private readonly string $fqcn,
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
        return new static($this->fqcn, $nullable);
    }

    public function fqcn(): string
    {
        return $this->fqcn;
    }

    public function shortName(): string
    {
        $parts = explode('\\', $this->fqcn);
        return end($parts);
    }
}
