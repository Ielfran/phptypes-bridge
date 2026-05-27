<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema;

use PHPTypeS\Bridge\Schema\Nodes\TypeNode;

final class PropertySchema
{
    public function __construct(
        private readonly string $name,
        private readonly TypeNode $typeNode,
        private readonly bool $optional = false,
        private readonly bool $readonly = false,
        private readonly ?string $docComment = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function typeNode(): TypeNode
    {
        return $this->typeNode;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function docComment(): ?string
    {
        return $this->docComment;
    }
}
