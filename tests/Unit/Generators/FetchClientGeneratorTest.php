<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Generators\FetchClientGenerator;
use PHPTypeS\Bridge\Tests\Support\SchemaFactory;

final class FetchClientGeneratorTest extends TestCase
{
    private FetchClientGenerator $generator;
    private string $output;

    protected function setUp(): void
    {
        $this->generator = new FetchClientGenerator();
        $schema          = SchemaFactory::make();
        $result          = $this->generator->generate($schema);
        $this->output    = $result->content();
    }

    // ── File metadata ─────────────────────────────────────────────────────────

    public function test_output_filename_is_api_client_ts(): void
    {
        $this->assertSame('api.client.ts', $this->generator->generate(SchemaFactory::make())->filename());
    }

    public function test_contains_do_not_edit_header(): void
    {
        $this->assertStringContainsString('DO NOT EDIT MANUALLY', $this->output);
    }

    // ── Imports ───────────────────────────────────────────────────────────────

    public function test_imports_types_from_api_types(): void
    {
        $this->assertStringContainsString("from './api.types'", $this->output);
        $this->assertStringContainsString('import type {', $this->output);
    }

    public function test_imports_schemas_from_api_schemas(): void
    {
        $this->assertStringContainsString("from './api.schemas'", $this->output);
    }

    public function test_imports_all_dto_types(): void
    {
        $this->assertStringContainsString('UserDto', $this->output);
        $this->assertStringContainsString('AddressDto', $this->output);
        $this->assertStringContainsString('CreateUserRequest', $this->output);
    }

    public function test_imports_all_dto_schemas(): void
    {
        $this->assertStringContainsString('UserDtoSchema', $this->output);
        $this->assertStringContainsString('AddressDtoSchema', $this->output);
    }

    // ── ApiError class ────────────────────────────────────────────────────────

    public function test_exports_api_error_class(): void
    {
        $this->assertStringContainsString('export class ApiError extends Error', $this->output);
    }

    public function test_api_error_has_status_and_body(): void
    {
        $this->assertStringContainsString('public readonly status: number', $this->output);
        $this->assertStringContainsString('public readonly body: string', $this->output);
    }

    // ── Factory function ──────────────────────────────────────────────────────

    public function test_exports_create_api_client_factory(): void
    {
        $this->assertStringContainsString('export function createApiClient(', $this->output);
    }

    public function test_factory_accepts_base_url_and_options(): void
    {
        $this->assertStringContainsString('baseUrl: string', $this->output);
        $this->assertStringContainsString('options: ApiClientOptions', $this->output);
    }

    public function test_factory_returns_object_with_all_endpoint_functions(): void
    {
        $this->assertStringContainsString('getUser,', $this->output);
        $this->assertStringContainsString('createUser,', $this->output);
        $this->assertStringContainsString('listUsers,', $this->output);
        $this->assertStringContainsString('deleteUser,', $this->output);
    }

    // ── GET endpoint ─────────────────────────────────────────────────────────

    public function test_get_endpoint_has_typed_path_param(): void
    {
        $this->assertStringContainsString('async function getUser(id: number)', $this->output);
    }

    public function test_get_endpoint_return_type_is_promise_of_dto(): void
    {
        $this->assertStringContainsString('Promise<UserDto>', $this->output);
    }

    public function test_get_endpoint_interpolates_path_param_in_url(): void
    {
        $this->assertStringContainsString('`${baseUrl}/api/v1/users/${id}`', $this->output);
    }

    public function test_get_endpoint_uses_get_method(): void
    {
        $this->assertStringContainsString("method: 'GET'", $this->output);
    }

    public function test_get_endpoint_parses_response_with_zod_schema(): void
    {
        $this->assertStringContainsString('UserDtoSchema.parse(await response.json())', $this->output);
    }

    public function test_get_endpoint_has_no_body(): void
    {
        // The getUser function specifically should not send a body
        $getPos  = strpos($this->output, 'async function getUser');
        $bodyPos = strpos($this->output, 'JSON.stringify(body)', $getPos ?: 0);
        $nextFnPos = strpos($this->output, 'async function createUser', $getPos ?: 0);

        // body stringify should not appear between getUser and createUser
        if ($bodyPos !== false && $nextFnPos !== false) {
            $this->assertGreaterThan($nextFnPos, $bodyPos);
        } else {
            // body stringify simply not found in getUser scope — that's correct
            $this->assertTrue(true);
        }
    }

    // ── POST endpoint ─────────────────────────────────────────────────────────

    public function test_post_endpoint_accepts_typed_body_param(): void
    {
        $this->assertStringContainsString('body: CreateUserRequest', $this->output);
    }

    public function test_post_endpoint_uses_post_method(): void
    {
        $this->assertStringContainsString("method: 'POST'", $this->output);
    }

    public function test_post_endpoint_sends_body_as_json(): void
    {
        $this->assertStringContainsString('JSON.stringify(body)', $this->output);
    }

    // ── DELETE endpoint ───────────────────────────────────────────────────────

    public function test_delete_endpoint_has_void_return_type(): void
    {
        $this->assertStringContainsString('Promise<void>', $this->output);
    }

    public function test_delete_endpoint_uses_delete_method(): void
    {
        $this->assertStringContainsString("method: 'DELETE'", $this->output);
    }

    // ── List endpoint (array response) ────────────────────────────────────────

    public function test_list_endpoint_has_array_return_type(): void
    {
        $this->assertStringContainsString('Promise<UserDto[]>', $this->output);
    }

    // ── Error handling ────────────────────────────────────────────────────────

    public function test_all_functions_check_response_ok(): void
    {
        $this->assertStringContainsString('if (!response.ok)', $this->output);
        $this->assertStringContainsString('throw new ApiError(response.status', $this->output);
    }

    // ── Headers ───────────────────────────────────────────────────────────────

    public function test_content_type_json_header_is_set(): void
    {
        $this->assertStringContainsString("'Content-Type': 'application/json'", $this->output);
    }

    public function test_custom_headers_merged_via_spread(): void
    {
        $this->assertStringContainsString('..._headers', $this->output);
    }

    // ── Options interface ─────────────────────────────────────────────────────

    public function test_exports_api_client_options_interface(): void
    {
        $this->assertStringContainsString('export interface ApiClientOptions', $this->output);
    }

    public function test_options_has_custom_fetch_override(): void
    {
        $this->assertStringContainsString('fetch?: typeof fetch', $this->output);
    }
}
