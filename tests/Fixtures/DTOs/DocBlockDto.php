<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\DTOs;

/**
 * A DTO whose array property types are declared only via @var annotations,
 * not PHP type hints. Tests that the DocBlockParser resolves them correctly.
 */
final class DocBlockDto
{
    public function __construct(
        public readonly int $id,

        /**
         * @param PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto[] $users
         */
        public readonly array $users,

        /**
         * @param string[] $tags
         */
        public readonly array $tags,
    ) {}
}
