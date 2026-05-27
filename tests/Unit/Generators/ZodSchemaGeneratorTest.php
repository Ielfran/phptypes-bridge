<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Generators\ZodSchemaGenerator;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\UnionTypeNode;
use PHPTypeS\Bridge\Tests\Support\SchemaFactory;

final class ZodSchemaGeneratorTest extends TestCase
{
    private ZodSchemaGenerator $generator;
    private string $output;

    protected function setUp(): void
    {
        $this->generator = new ZodSchemaGenerator();
        $schema          = SchemaFactory::make();
        $result          = $this->generator->generate($schema);
        $this->output    = $result->content();
    }

    // ── File metadata ─────────────────────────────────────────────────────────

    public function test_output_filename_is_api_schemas_ts(): void
    {
        $schema = SchemaFactory::make();
        $this->assertSame('api.schemas.ts', $this->generator->generate($schema)->filename());
    }

    public function test_imports_zod(): void
    {
        $this->assertStringContainsString("import { z } from 'zod';", $this->output);
    }

    // ── Enum ──────────────────────────────────────────────────────────────────

    public function test_enum_emits_z_enum_with_all_values(): void
    {
        $this->assertStringContainsString(
            "export const UserStatusSchema = z.enum(['active', 'inactive', 'banned']);",
            $this->output
        );
    }

    public function test_enum_emits_inferred_type(): void
    {
        $this->assertStringContainsString(
            'export type UserStatus = z.infer<typeof UserStatusSchema>;',
            $this->output
        );
    }

    // ── DTO schema ────────────────────────────────────────────────────────────

    public function test_dto_emits_z_object(): void
    {
        $this->assertStringContainsString('export const UserDtoSchema = z.object({', $this->output);
        $this->assertStringContainsString('export const AddressDtoSchema = z.object({', $this->output);
    }

    public function test_dto_emits_inferred_type(): void
    {
        $this->assertStringContainsString(
            'export type UserDto = z.infer<typeof UserDtoSchema>;',
            $this->output
        );
    }

    // ── Scalar types ──────────────────────────────────────────────────────────

    public function test_string_property_emits_z_string(): void
    {
        $this->assertStringContainsString('email: z.string()', $this->output);
    }

    public function test_int_property_emits_z_number(): void
    {
        $this->assertStringContainsString('id: z.number()', $this->output);
    }

    // ── Nullable ──────────────────────────────────────────────────────────────

    public function test_nullable_property_appends_nullable(): void
    {
        // postcode is nullable and optional
        $this->assertStringContainsString('postcode: z.string().nullable().optional()', $this->output);
    }

    public function test_required_non_nullable_has_no_nullable_call(): void
    {
        // email is required non-nullable — should not have .nullable()
        $this->assertMatchesRegularExpression('/email: z\.string\(\),/', $this->output);
    }

    // ── Optional ─────────────────────────────────────────────────────────────

    public function test_optional_property_appends_optional(): void
    {
        $this->assertStringContainsString('.optional()', $this->output);
    }

    public function test_nullable_before_optional_in_chain(): void
    {
        // Zod convention: .nullable() before .optional()
        $pos_nullable = strpos($this->output, '.nullable()');
        $pos_optional = strpos($this->output, '.optional()');

        $this->assertNotFalse($pos_nullable);
        $this->assertNotFalse($pos_optional);
        $this->assertLessThan($pos_optional, $pos_nullable);
    }

    // ── Ref types ─────────────────────────────────────────────────────────────

    public function test_ref_property_uses_schema_name(): void
    {
        $this->assertStringContainsString('address: AddressDtoSchema', $this->output);
    }

    public function test_enum_ref_property_uses_enum_schema(): void
    {
        $this->assertStringContainsString('status: UserStatusSchema', $this->output);
    }

    // ── renderTypeNodeZod directly ────────────────────────────────────────────

    public function test_bool_maps_to_z_boolean(): void
    {
        $schema = SchemaFactory::make();
        $node   = new ScalarTypeNode('bool');
        $result = $this->generator->renderTypeNodeZod($node, $schema);

        $this->assertSame('z.boolean()', $result);
    }

    public function test_array_of_strings_emits_z_array_z_string(): void
    {
        $schema = SchemaFactory::make();
        $node   = new ArrayTypeNode(new ScalarTypeNode('string'));
        $result = $this->generator->renderTypeNodeZod($node, $schema);

        $this->assertSame('z.array(z.string())', $result);
    }

    public function test_union_of_two_types_emits_z_union(): void
    {
        $schema = SchemaFactory::make();
        $node   = new UnionTypeNode([new ScalarTypeNode('string'), new ScalarTypeNode('int')]);
        $result = $this->generator->renderTypeNodeZod($node, $schema);

        $this->assertSame('z.union([z.string(), z.number()])', $result);
    }

    public function test_mixed_maps_to_z_unknown(): void
    {
        $schema = SchemaFactory::make();
        $node   = new ScalarTypeNode('mixed');
        $result = $this->generator->renderTypeNodeZod($node, $schema);

        $this->assertSame('z.unknown()', $result);
    }

    // ── Ordering ──────────────────────────────────────────────────────────────

    public function test_address_schema_declared_before_user_dto_schema(): void
    {
        // AddressDto must appear before UserDto since UserDto refs AddressDto
        $addressPos = strpos($this->output, 'export const AddressDtoSchema');
        $userPos    = strpos($this->output, 'export const UserDtoSchema');

        $this->assertNotFalse($addressPos);
        $this->assertNotFalse($userPos);
        $this->assertLessThan($userPos, $addressPos);
    }
}
