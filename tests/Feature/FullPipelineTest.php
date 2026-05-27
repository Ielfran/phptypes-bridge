<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Feature;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Parser\ReflectionParser;
use PHPTypeS\Bridge\Tests\Fixtures\Controllers\UserController;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\AddressDto;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\CreateUserRequest;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto;


final class FullPipelineTest extends TestCase
{
    public function test_parser_finds_all_endpoints_from_fixture_directory(): void
    {
        $parser = new ReflectionParser();
        $schema = $parser->parse([
            __DIR__ . '/../Fixtures/Controllers',
            __DIR__ . '/../Fixtures/DTOs',
        ]);

        $this->assertFalse($schema->isEmpty());
        $this->assertCount(4, $schema->endpoints());
    }

    public function test_all_dtos_are_registered_after_full_scan(): void
    {
        $parser = new ReflectionParser();
        $schema = $parser->parse([
            __DIR__ . '/../Fixtures/Controllers',
            __DIR__ . '/../Fixtures/DTOs',
        ]);

        $this->assertTrue($schema->hasDto(UserDto::class));
        $this->assertTrue($schema->hasDto(AddressDto::class));
        $this->assertTrue($schema->hasDto(CreateUserRequest::class));
    }

    public function test_endpoint_names_are_correct(): void
    {
        $parser = new ReflectionParser();
        $schema = $parser->parse([__DIR__ . '/../Fixtures/Controllers']);

        $names = array_map(fn($e) => $e->name(), $schema->endpoints());

        $this->assertContains('listUsers', $names);
        $this->assertContains('getUser', $names);
        $this->assertContains('createUser', $names);
        $this->assertContains('deleteUser', $names);
    }

    public function test_non_annotated_methods_are_excluded(): void
    {
        $parser = new ReflectionParser();
        $schema = $parser->parse([__DIR__ . '/../Fixtures/Controllers']);

        $names = array_map(fn($e) => $e->name(), $schema->endpoints());

        $this->assertNotContains('internalHelper', $names);
    }

    public function test_controller_fqcn_is_set_on_endpoints(): void
    {
        $parser = new ReflectionParser();
        $schema = $parser->parse([__DIR__ . '/../Fixtures/Controllers']);

        foreach ($schema->endpoints() as $endpoint) {
            $this->assertSame(UserController::class, $endpoint->controllerFqcn());
        }
    }

    public function test_scanning_nonexistent_directory_throws(): void
    {
        $this->expectException(\PHPTypeS\Bridge\Exceptions\ParserException::class);

        $parser = new ReflectionParser();
        $parser->parse(['/path/that/does/not/exist']);
    }

    public function test_empty_directory_returns_empty_schema(): void
    {
        $emptyDir = sys_get_temp_dir() . '/PHPTypeS_bridge_test_empty';
        @mkdir($emptyDir);

        $parser = new ReflectionParser();
        $schema = $parser->parse([$emptyDir]);

        $this->assertTrue($schema->isEmpty());

        @rmdir($emptyDir);
    }
}
