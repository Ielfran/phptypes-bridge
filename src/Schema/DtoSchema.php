<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema;

final class DtoSchema
{
    public function __construct(
        private readonly string $fqcn,
        private readonly string $shortName,
        private readonly array $properties,
        private readonly array $generics = [],
        private readonly ?string $docComment = null,
    ) {}

    public function fqcn(): string
    {
        return $this->fqcn;
    }

    public function shortName(): string
    {
        return $this->shortName;
    }

    public function properties(): array
    {
        return $this->properties;
    }

 
    public function generics(): array
    {
        return $this->generics;
    }

    public function isGeneric(): bool
    {
        return $this->generics !== [];
    }

    public function docComment(): ?string
    {
        return $this->docComment;
    }
}
