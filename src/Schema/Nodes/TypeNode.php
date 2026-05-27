<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema\Nodes;


abstract class TypeNode
{
    
    abstract public function kind(): string;

    abstract public function isNullable(): bool;

    abstract public function withNullable(bool $nullable = true): static;
}
