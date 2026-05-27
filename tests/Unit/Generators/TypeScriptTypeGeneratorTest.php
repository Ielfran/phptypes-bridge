<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Generators\TypeScriptTypeGenerator;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\UnionTypeNode;
use PHPTypeS\Bridge\Tests\Support\SchemaFactory;

final class TypeScriptTypeGeneratorTest extends TestCase
{
    private TypeScriptTypeGenerator $generator;
    private string $output;

    protected function setUp(): void
    {
        $this->generator = new TypeScriptTypeGenerator();
        $schema          = SchemaFactory::make();
        $result          = $this->generator->generate($schema);
        $this->output    = $result->content();
    }

    // ── File metadata ─────────────────────────────────────────────────────────

    public function test_output_filename_is_api_types_ts(): void
    {
        $schema = SchemaFactory::make();
        $result = $this->generator->generate($schema);

        $this->assertSame('api.types.ts', $result->filename());
    }

    public function test_output_contains_do_not_edit_header(): void
    {
        $this->assertStringContainsString('DO NOT EDIT MANUALLY', $this->output);
    }

    // ── Enum generation ───────────────────────────────────────────────────────

    public function test_enum_emits_as_const_object(): void
    {
        $this->assertStringContainsString('export const UserStatus = {', $this->output);
        $this->assertStringContainsString("} as const;", $this->output);
    }

    public function test_enum_contains_all_cases_with_values(): void
    {
        $this->assertStringContainsString("Active: 'active'", $this->output);
        $this->assertStringContainsString("Inactive: 'inactive'", $this->output);
        $this->assertStringContainsString("Banned: 'banned'", $this->output);
    }

    public function test_enum_emits_type_alias(): void
    {
        $this->assertStringContainsString(
            'export type UserStatus = typeof UserStatus[keyof typeof UserStatus];',
            $this->output
        );
    }

    // ── Interface generation ──────────────────────────────────────────────────

    public function test_dto_emits_interface(): void
    {
        $this->assertStringContainsString('export interface UserDto {', $this->output);
        $this->assertStringContainsString('export interface AddressDto {', $this->output);
        $this->assertStringContainsString('export interface CreateUserRequest {', $this->output);
    }

    public function test_string_property_emits_correctly(): void
    {
        $this->assertStringContainsString('email: string;', $this->output);
    }

    public function test_int_property_emits_as_number(): void
    {
        $this->assertStringContainsString('id: number;', $this->output);
    }

    // ── Nullable ──────────────────────────────────────────────────────────────

    public function test_nullable_string_emits_union_with_null(): void
    {
        // postcode?: string | null  (optional AND nullable)
        $this->assertStringContainsString('postcode?: string | null;', $this->output);
    }

    public function test_required_nullable_emits_t_or_null(): void
    {
        // bio: string | null  (nullable but required — wait, it's also optional in our schema)
        $this->assertStringContainsString('bio?: string | null;', $this->output);
    }

    // ── Optional ─────────────────────────────────────────────────────────────

    public function test_optional_property_uses_question_mark(): void
    {
        $this->assertStringContainsString('bio?:', $this->output);
    }

    public function test_required_property_has_no_question_mark(): void
    {
        // 'id: number;' — no ? before the colon
        $this->assertMatchesRegularExpression('/^\s+id: number;/m', $this->output);
    }

    // ── Ref types ─────────────────────────────────────────────────────────────

    public function test_dto_ref_property_uses_short_name(): void
    {
        $this->assertStringContainsString('address: AddressDto;', $this->output);
    }

    public function test_enum_ref_property_uses_enum_name(): void
    {
        $this->assertStringContainsString('status: UserStatus;', $this->output);
    }

    // ── Doc comments ─────────────────────────────────────────────────────────

    public function test_doc_comment_is_emitted_as_jsdoc(): void
    {
        $this->assertStringContainsString('/** User biography text */', $this->output);
    }

    // ── renderTypeNode directly ───────────────────────────────────────────────

    public function test_render_array_of_scalars(): void
    {
        $schema = SchemaFactory::make();
        $node   = new ArrayTypeNode(new ScalarTypeNode('string'));
        $result = $this->generator->renderTypeNode($node, $schema);

        $this->assertSame('string[]', $result);
    }

    public function test_render_nullable_array(): void
    {
        $schema = SchemaFactory::make();
        $node   = (new ArrayTypeNode(new ScalarTypeNode('string')))->withNullable(true);
        $result = $this->generator->renderTypeNode($node, $schema);

        $this->assertSame('string[] | null', $result);
    }

    public function test_render_union_of_two_scalars(): void
    {
        $schema = SchemaFactory::make();
        $node   = new UnionTypeNode([
            new ScalarTypeNode('string'),
            new ScalarTypeNode('int'),
        ]);
        $result = $this->generator->renderTypeNode($node, $schema);

        $this->assertSame('string | number', $result);
    }

    public function test_render_array_of_union_wraps_in_parens(): void
    {
        $schema  = SchemaFactory::make();
        $union   = new UnionTypeNode([new ScalarTypeNode('string'), new ScalarTypeNode('int')]);
        $node    = new ArrayTypeNode($union);
        $result  = $this->generator->renderTypeNode($node, $schema);

        $this->assertSame('(string | number)[]', $result);
    }

    // ── Ordering ──────────────────────────────────────────────────────────────

    public function test_enums_appear_before_interfaces(): void
    {
        $enumPos      = strpos($this->output, 'export const UserStatus');
        $interfacePos = strpos($this->output, 'export interface AddressDto');

        $this->assertNotFalse($enumPos);
        $this->assertNotFalse($interfacePos);
        $this->assertLessThan($interfacePos, $enumPos);
    }
}
