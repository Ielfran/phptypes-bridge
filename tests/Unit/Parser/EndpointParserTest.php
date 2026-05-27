<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use PHPTypeS\Bridge\Parser\DtoParser;
use PHPTypeS\Bridge\Parser\EndpointParser;
use PHPTypeS\Bridge\Schema\ApiSchema;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Tests\Fixtures\Controllers\UserController;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\CreateUserRequest;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;

final class EndpointParserTest extends TestCase
{
    private EndpointParser $parser;
    private ApiSchema $schema;

    protected function setUp(): void
    {
        $mapper       = new TypeMapper();
        $this->parser = new EndpointParser($mapper, new DtoParser($mapper));
        $this->schema = new ApiSchema();

        $this->parser->parse(new ReflectionClass(UserController::class), $this->schema);
    }

    // ── Endpoint count ────────────────────────────────────────────────────────

    public function test_discovers_only_annotated_methods(): void
    {
        // index, show, store, destroy — 4 endpoints. internalHelper is excluded.
        $this->assertCount(4, $this->schema->endpoints());
    }

    // ── Path prefix from ApiGroup ─────────────────────────────────────────────

    public function test_group_prefix_is_prepended_to_all_paths(): void
    {
        foreach ($this->schema->endpoints() as $endpoint) {
            $this->assertStringStartsWith('/api/v1/', $endpoint->path());
        }
    }

    // ── HTTP method ───────────────────────────────────────────────────────────

    public function test_get_method_is_resolved(): void
    {
        $endpoint = $this->findEndpoint('getUser');

        $this->assertNotNull($endpoint);
        $this->assertSame('GET', $endpoint->httpMethod());
    }

    public function test_post_method_is_resolved(): void
    {
        $endpoint = $this->findEndpoint('createUser');

        $this->assertNotNull($endpoint);
        $this->assertSame('POST', $endpoint->httpMethod());
    }

    public function test_delete_method_is_resolved(): void
    {
        $endpoint = $this->findEndpoint('deleteUser');

        $this->assertNotNull($endpoint);
        $this->assertSame('DELETE', $endpoint->httpMethod());
    }

    // ── Path ──────────────────────────────────────────────────────────────────

    public function test_full_path_includes_prefix_and_route(): void
    {
        $endpoint = $this->findEndpoint('getUser');

        $this->assertNotNull($endpoint);
        $this->assertSame('/api/v1/users/{id}', $endpoint->path());
    }

    // ── Path params ───────────────────────────────────────────────────────────

    public function test_path_params_are_extracted_from_path(): void
    {
        $endpoint = $this->findEndpoint('getUser');

        $this->assertNotNull($endpoint);
        $this->assertSame(['id'], $endpoint->pathParams());
    }

    public function test_no_path_params_for_collection_endpoint(): void
    {
        $endpoint = $this->findEndpoint('listUsers');

        $this->assertNotNull($endpoint);
        $this->assertSame([], $endpoint->pathParams());
    }

    // ── Tags ──────────────────────────────────────────────────────────────────

    public function test_group_tags_are_merged_onto_endpoints(): void
    {
        $endpoint = $this->findEndpoint('getUser');

        $this->assertNotNull($endpoint);
        $this->assertContains('users', $endpoint->tags());
    }

    // ── Response type ─────────────────────────────────────────────────────────

    public function test_response_type_is_ref_to_user_dto(): void
    {
        $endpoint = $this->findEndpoint('getUser');

        $this->assertNotNull($endpoint);
        $this->assertInstanceOf(RefTypeNode::class, $endpoint->responseType());
        $this->assertSame(UserDto::class, $endpoint->responseType()->fqcn());
    }

    public function test_void_endpoint_has_null_response_type(): void
    {
        $endpoint = $this->findEndpoint('deleteUser');

        $this->assertNotNull($endpoint);
        $this->assertNull($endpoint->responseType());
    }

    // ── Request type ──────────────────────────────────────────────────────────

    public function test_post_endpoint_has_request_type(): void
    {
        $endpoint = $this->findEndpoint('createUser');

        $this->assertNotNull($endpoint);
        $this->assertInstanceOf(RefTypeNode::class, $endpoint->requestType());
        $this->assertSame(CreateUserRequest::class, $endpoint->requestType()->fqcn());
    }

    public function test_get_endpoint_has_no_request_type(): void
    {
        $endpoint = $this->findEndpoint('getUser');

        $this->assertNotNull($endpoint);
        $this->assertNull($endpoint->requestType());
    }

    // ── DTO auto-registration ─────────────────────────────────────────────────

    public function test_referenced_dtos_are_registered_in_schema(): void
    {
        $this->assertTrue($this->schema->hasDto(UserDto::class));
        $this->assertTrue($this->schema->hasDto(CreateUserRequest::class));
    }

    public function test_nested_dtos_are_recursively_registered(): void
    {
        // AddressDto is a property of UserDto — should be auto-registered
        $this->assertTrue(
            $this->schema->hasDto(\PHPTypeS\Bridge\Tests\Fixtures\DTOs\AddressDto::class)
        );
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function findEndpoint(string $name): ?\PHPTypeS\Bridge\Schema\EndpointSchema
    {
        foreach ($this->schema->endpoints() as $endpoint) {
            if ($endpoint->name() === $name) {
                return $endpoint;
            }
        }

        return null;
    }
}
