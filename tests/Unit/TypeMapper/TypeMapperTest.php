<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\TypeMapper;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Exceptions\UnsupportedTypeException;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\UnionTypeNode;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;

final class TypeMapperTest extends TestCase
{
    private TypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TypeMapper();
    }

    // ── Scalars ───────────────────────────────────────────────────────────────

    public function test_maps_string(): void
    {
        $node = $this->mapper->fromString('string', 'test');

        $this->assertInstanceOf(ScalarTypeNode::class, $node);
        $this->assertSame('string', $node->phpType());
        $this->assertFalse($node->isNullable());
    }

    public function test_maps_int(): void
    {
        $node = $this->mapper->fromString('int', 'test');

        $this->assertInstanceOf(ScalarTypeNode::class, $node);
        $this->assertSame('int', $node->phpType());
    }

    public function test_maps_float(): void
    {
        $node = $this->mapper->fromString('float', 'test');

        $this->assertInstanceOf(ScalarTypeNode::class, $node);
        $this->assertSame('number', $node->tsType());
    }

    public function test_maps_bool(): void
    {
        $node = $this->mapper->fromString('bool', 'test');

        $this->assertInstanceOf(ScalarTypeNode::class, $node);
        $this->assertSame('boolean', $node->tsType());
    }

    public function test_maps_mixed_to_unknown(): void
    {
        $node = $this->mapper->fromString('mixed', 'test');

        $this->assertInstanceOf(ScalarTypeNode::class, $node);
        $this->assertSame('unknown', $node->tsType());
    }

    // ── Nullable ──────────────────────────────────────────────────────────────

    public function test_maps_nullable_shorthand(): void
    {
        $node = $this->mapper->fromString('?string', 'test');

        $this->assertInstanceOf(ScalarTypeNode::class, $node);
        $this->assertTrue($node->isNullable());
    }

    public function test_maps_union_with_null(): void
    {
        $node = $this->mapper->fromString('string|null', 'test');

        $this->assertInstanceOf(ScalarTypeNode::class, $node);
        $this->assertTrue($node->isNullable());
    }

    // ── Arrays ────────────────────────────────────────────────────────────────

    public function test_maps_untyped_array(): void
    {
        $node = $this->mapper->fromString('array', 'test');

        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $this->assertInstanceOf(ScalarTypeNode::class, $node->itemType());
        $this->assertSame('mixed', $node->itemType()->phpType());
    }

    public function test_maps_typed_array_with_bracket_syntax(): void
    {
        $node = $this->mapper->fromString('string[]', 'test');

        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $this->assertInstanceOf(ScalarTypeNode::class, $node->itemType());
        $this->assertSame('string', $node->itemType()->phpType());
    }

    public function test_maps_list_generic_syntax(): void
    {
        $node = $this->mapper->fromString('list<int>', 'test');

        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $this->assertSame('int', $node->itemType()->phpType());
    }

    public function test_maps_array_generic_syntax(): void
    {
        $node = $this->mapper->fromString('array<string>', 'test');

        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $this->assertSame('string', $node->itemType()->phpType());
    }

    public function test_maps_nullable_array(): void
    {
        $node = $this->mapper->fromString('?string[]', 'test');

        $this->assertInstanceOf(ArrayTypeNode::class, $node);
        $this->assertTrue($node->isNullable());
    }

    // ── Unions ────────────────────────────────────────────────────────────────

    public function test_maps_union_of_two_scalars(): void
    {
        $node = $this->mapper->fromString('string|int', 'test');

        $this->assertInstanceOf(UnionTypeNode::class, $node);
        $this->assertCount(2, $node->types());
        $this->assertFalse($node->isNullable());
    }

    public function test_maps_nullable_union_strips_null_and_sets_flag(): void
    {
        $node = $this->mapper->fromString('string|int|null', 'test');

        $this->assertInstanceOf(UnionTypeNode::class, $node);
        $this->assertCount(2, $node->types());
        $this->assertTrue($node->isNullable());
    }

    // ── Refs ──────────────────────────────────────────────────────────────────

    public function test_maps_known_class_to_ref(): void
    {
        $node = $this->mapper->fromString(
            \PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto::class,
            'test'
        );

        $this->assertInstanceOf(RefTypeNode::class, $node);
        $this->assertSame(
            \PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto::class,
            $node->fqcn()
        );
        $this->assertSame('UserDto', $node->shortName());
    }

    public function test_maps_nullable_ref(): void
    {
        $node = $this->mapper->fromString(
            '?' . \PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto::class,
            'test'
        );

        $this->assertInstanceOf(RefTypeNode::class, $node);
        $this->assertTrue($node->isNullable());
    }

    // ── Type aliases ──────────────────────────────────────────────────────────

    public function test_type_alias_overrides_unknown_class(): void
    {
        $aliasNode = new ScalarTypeNode('string');
        $mapper    = new TypeMapper(['Some\\CarbonDate' => $aliasNode]);

        $node = $mapper->fromString('Some\\CarbonDate', 'test');

        $this->assertSame($aliasNode, $node);
    }

    // ── Unsupported ───────────────────────────────────────────────────────────

    public function test_throws_for_unknown_type_without_alias(): void
    {
        $this->expectException(UnsupportedTypeException::class);

        $this->mapper->fromString('Some\\Unresolvable\\Class\\Name\\ThatDoesNotExist', 'test');
    }

    // ── TS type output ────────────────────────────────────────────────────────

    /**
     * @dataProvider tsTypeProvider
     */
    public function test_scalar_ts_type(string $phpType, string $expectedTs): void
    {
        $node = new ScalarTypeNode($phpType);
        $this->assertSame($expectedTs, $node->tsType());
    }

    /** @return array<string, array{string, string}> */
    public static function tsTypeProvider(): array
    {
        return [
            'string'  => ['string', 'string'],
            'int'     => ['int', 'number'],
            'float'   => ['float', 'number'],
            'bool'    => ['bool', 'boolean'],
            'null'    => ['null', 'null'],
            'void'    => ['void', 'void'],
            'mixed'   => ['mixed', 'unknown'],
            'object'  => ['object', 'unknown'],
        ];
    }
}
