<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Exceptions;

use RuntimeException;

final class UnsupportedTypeException extends RuntimeException
{
    public static function forType(string $phpType, string $context): self
    {
        return new self(
            "Unsupported PHP type \"{$phpType}\" encountered in {$context}. "
            . 'Add a typeAlias mapping in bridge.php to handle this type.'
        );
    }
}
