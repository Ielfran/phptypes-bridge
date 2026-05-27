<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\DTOs;

final class CreateUserRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $bio = null,
    ) {}
}
