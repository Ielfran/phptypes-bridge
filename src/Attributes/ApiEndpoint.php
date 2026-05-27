<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_METHOD)]
final class ApiEndpoint
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly ?string $name = null,
        public readonly ?string $responseType = null,
        public readonly array $tags = [],
    ) {}
}
