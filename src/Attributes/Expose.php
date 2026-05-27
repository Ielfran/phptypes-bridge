<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Expose
{
    public function __construct(
        /** Override the serialised property name in the TS output */
        public readonly ?string $name = null,

        /** When true, the TS interface uses `prop?: T` instead of `prop: T` */
        public readonly bool $optional = false,

        /** Emitted as a JSDoc comment above the property in the TS output */
        public readonly ?string $description = null,
    ) {}
}
