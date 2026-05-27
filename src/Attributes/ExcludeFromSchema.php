<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Attributes;

use Attribute;

/**
 * Excludes a DTO property from schema generation.
 *
 * Used for internal fields that are serialised in PHP but should NOT
 * be part of the TypeScript contract — computed fields, audit trails,
 * internal flags, etc.
 *
 * Usage:
 *
 *   #[ExcludeFromSchema]
 *   public readonly string $internalAuditToken,
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class ExcludeFromSchema
{
    public function __construct() {}
}
