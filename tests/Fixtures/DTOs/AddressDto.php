<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\DTOs;

use PHPTypeS\Bridge\Attributes\Expose;

/**
 * A nested DTO used to test recursive type resolution.
 */
final class AddressDto
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country,
        #[Expose(optional: true)]
        public readonly ?string $postcode = null,
    ) {}
}
