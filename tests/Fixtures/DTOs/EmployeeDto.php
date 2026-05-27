<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\DTOs;

final class EmployeeDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        /** The team this employee belongs to (may be null for unassigned employees) */
        public readonly ?TeamDto $team = null,
    ) {}
}
