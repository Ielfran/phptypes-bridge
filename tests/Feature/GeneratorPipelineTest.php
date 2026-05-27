<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Feature;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Generators\FetchClientGenerator;
use PHPTypeS\Bridge\Generators\TypeScriptTypeGenerator;
use PHPTypeS\Bridge\Generators\ZodSchemaGenerator;
use PHPTypeS\Bridge\Parser\ReflectionParser;


final class GeneratorPipelineTest extends TestCase
{
    private string $typesOutput;
    private string $schemasOutput;
    private string $clientOutput;

    protected function setUp(): void
    {
        $parser = new ReflectionParser();
        $schema = $parser->parse([
            __DIR__ . '/../Fixtures/Controllers',
            __DIR__ . '/../Fixtures/DTOs',
        ]);

        $this->typesOutput  = (new TypeScriptTypeGenerator())->generate($schema)->content();
        $this->schemasOutput = (new ZodSchemaGenerator())->generate($schema)->content();
        $this->clientOutput = (new FetchClientGenerator())->generate($schema)->content();
    }

    public function test_every_type_in_types_file_has_matching_schema(): void
    {
        preg_match_all('/export interface (\w+)/', $this->typesOutput, $typeMatches);
        $typeNames = $typeMatches[1];

        foreach ($typeNames as $name) {
            $this->assertStringContainsString(
                "export const {$name}Schema",
                $this->schemasOutput,
                "Expected schema for interface {$name} to exist in api.schemas.ts"
            );
        }
    }

    public function test_every_schema_in_schemas_file_is_imported_in_client(): void
    {
        preg_match_all('/export const (\w+Schema)/', $this->schemasOutput, $schemaMatches);
        $schemaNames = $schemaMatches[1];

        foreach ($schemaNames as $schemaName) {
            $this->assertStringContainsString(
                $schemaName,
                $this->clientOutput,
                "Expected {$schemaName} to be referenced in api.client.ts"
            );
        }
    }

    public function test_types_output_contains_user_dto_interface(): void
    {
        $this->assertStringContainsString('export interface UserDto', $this->typesOutput);
    }

    public function test_types_output_renames_exposed_property(): void
    {
        $this->assertStringContainsString('full_name:', $this->typesOutput);
    }

    public function test_types_output_excludes_internal_token(): void
    {
        $this->assertStringNotContainsString('internalToken', $this->typesOutput);
    }

    public function test_schemas_output_has_zod_import(): void
    {
        $this->assertStringContainsString("import { z } from 'zod'", $this->schemasOutput);
    }

    public function test_schemas_output_contains_user_dto_schema(): void
    {
        $this->assertStringContainsString('export const UserDtoSchema = z.object({', $this->schemasOutput);
    }

    public function test_schemas_output_excludes_internal_token(): void
    {
        $this->assertStringNotContainsString('internalToken', $this->schemasOutput);
    }

    public function test_client_output_has_create_api_client(): void
    {
        $this->assertStringContainsString('export function createApiClient', $this->clientOutput);
    }

    public function test_client_output_has_all_four_endpoints(): void
    {
        $this->assertStringContainsString('getUser', $this->clientOutput);
        $this->assertStringContainsString('createUser', $this->clientOutput);
        $this->assertStringContainsString('listUsers', $this->clientOutput);
        $this->assertStringContainsString('deleteUser', $this->clientOutput);
    }

    public function test_client_output_has_api_error_class(): void
    {
        $this->assertStringContainsString('export class ApiError', $this->clientOutput);
    }

    public function test_generator_output_writes_files_to_directory(): void
    {
        $dir = sys_get_temp_dir() . '/PHPTypeS_bridge_output_' . uniqid();
        mkdir($dir);

        $parser = new ReflectionParser();
        $schema = $parser->parse([__DIR__ . '/../Fixtures/Controllers']);

        $outputs = [
            (new TypeScriptTypeGenerator())->generate($schema),
            (new ZodSchemaGenerator())->generate($schema),
            (new FetchClientGenerator())->generate($schema),
        ];

        foreach ($outputs as $output) {
            $output->writeTo($dir);
        }

        $this->assertFileExists("{$dir}/api.types.ts");
        $this->assertFileExists("{$dir}/api.schemas.ts");
        $this->assertFileExists("{$dir}/api.client.ts");

        // Cleanup
        foreach (glob("{$dir}/*.ts") as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}
