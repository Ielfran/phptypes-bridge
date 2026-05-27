<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\DTOs;

/**
 * Generic wrapper used to test array-of-DTO response types in the generators.
 */
final class PaginatedResult
{
    /**
     * @param UserDto[] $data
     */
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
    ) {}
}
