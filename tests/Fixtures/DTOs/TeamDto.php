<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\DTOs;

/**
 * TeamDto references EmployeeDto.
 * EmployeeDto references TeamDto.
 * Together they form a circular reference the parser must not loop on.
 */
final class TeamDto
{
    /**
     * @param EmployeeDto[] $members
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $members,
    ) {}
}
