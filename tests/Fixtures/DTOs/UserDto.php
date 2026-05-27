<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\DTOs;

use PHPTypeS\Bridge\Attributes\Expose;
use PHPTypeS\Bridge\Attributes\ExcludeFromSchema;

/**
 * Primary test fixture DTO covering:
 *   - Scalar types (string, int, bool)
 *   - Nullable types (?string)
 *   - Nested DTO ref (AddressDto)
 *   - Array of DTO refs (AddressDto[])
 *   - Optional properties (defaults)
 *   - Expose attribute with name override
 *   - ExcludeFromSchema attribute
 */
final class UserDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,

        #[Expose(name: 'full_name')]
        public readonly string $name,

        public readonly bool $active,

        public readonly AddressDto $address,

        /** @var AddressDto[] */
        public readonly array $previousAddresses,

        public readonly ?string $bio = null,

        #[Expose(optional: true)]
        public readonly ?string $avatarUrl = null,

        #[ExcludeFromSchema]
        public readonly string $internalToken = '',
    ) {}
}
