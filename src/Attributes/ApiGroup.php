<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ApiGroup
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly string $prefix = '',
        public readonly array $tags = [],
    ) {}
}
