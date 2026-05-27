<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\Controllers;

use PHPTypeS\Bridge\Attributes\ApiEndpoint;
use PHPTypeS\Bridge\Attributes\ApiGroup;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\CreateUserRequest;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto;

#[ApiGroup('/api/v1', tags: ['users'])]
final class UserController
{
    #[ApiEndpoint('GET', '/users', name: 'listUsers', tags: ['users'])]
    public function index(): array
    {
        return [];
    }

    #[ApiEndpoint('GET', '/users/{id}', name: 'getUser')]
    public function show(int $id): UserDto
    {
        return new UserDto(0, '', '', false, new \PHPTypeS\Bridge\Tests\Fixtures\DTOs\AddressDto('', '', ''), []);
    }

    #[ApiEndpoint('POST', '/users', name: 'createUser')]
    public function store(CreateUserRequest $request): UserDto
    {
        return new UserDto(0, '', '', false, new \PHPTypeS\Bridge\Tests\Fixtures\DTOs\AddressDto('', '', ''), []);
    }

    #[ApiEndpoint('DELETE', '/users/{id}', name: 'deleteUser')]
    public function destroy(int $id): void
    {
    }

    // No attribute — should NOT appear in the schema
    public function internalHelper(): void
    {
    }
}
