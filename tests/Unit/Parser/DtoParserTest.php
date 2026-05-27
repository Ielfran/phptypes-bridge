<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use PHPTypeS\Bridge\Parser\DtoParser;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\AddressDto;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\CreateUserRequest;

final class DtoParserTest extends TestCase
{
    private DtoParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DtoParser(new TypeMapper());
    }

    // ── Basic structure ───────────────────────────────────────────────────────

    public function test_parses_fqcn_and_short_name(): void
    {
        $schema = $this->parser->parse(new ReflectionClass(AddressDto::class));

        $this->assertSame(AddressDto::class, $schema->fqcn());
        $this->assertSame('AddressDto', $schema->shortName());
    }

    public function test_parses_correct_property_count_for_address_dto(): void
    {
        $schema = $this->parser->parse(new ReflectionClass(AddressDto::class));

        // street, city, country, postcode — 4 public constructor params
        $this->assertCount(4, $schema->properties());
    }

    // ── Scalar types ──────────────────────────────────────────────────────────

    public function test_parses_string_property(): void
    {
        $schema = $this->parser->parse(new ReflectionClass(AddressDto::class));
        $prop   = $schema->properties()[0]; // street

        $this->assertSame('street', $prop->name());
        $this->assertInstanceOf(ScalarTypeNode::class, $prop->typeNode());
        $this->assertSame('string', $prop->typeNode()->phpType());
        $this->assertFalse($prop->isOptional());
    }

    // ── Nullable ──────────────────────────────────────────────────────────────

    public function test_parses_nullable_string_property(): void
    {
        $schema    = $this->parser->parse(new ReflectionClass(AddressDto::class));
        $postcode  = $this->findProperty($schema->properties(), 'postcode');

        $this->assertNotNull($postcode);
        $this->assertTrue($postcode->typeNode()->isNullable());
    }

    // ── Optional ─────────────────────────────────────────────────────────────

    public function test_property_with_default_is_optional(): void
    {
        $schema   = $this->parser->parse(new ReflectionClass(AddressDto::class));
        $postcode = $this->findProperty($schema->properties(), 'postcode');

        $this->assertNotNull($postcode);
        $this->assertTrue($postcode->isOptional());
    }

    public function test_required_property_is_not_optional(): void
    {
        $schema = $this->parser->parse(new ReflectionClass(AddressDto::class));
        $street = $this->findProperty($schema->properties(), 'street');

        $this->assertNotNull($street);
        $this->assertFalse($street->isOptional());
    }

    // ── Attribute: ExcludeFromSchema ──────────────────────────────────────────

    public function test_excluded_property_is_absent_from_schema(): void
    {
        $schema    = $this->parser->parse(new ReflectionClass(UserDto::class));
        $propNames = array_map(fn($p) => $p->name(), $schema->properties());

        $this->assertNotContains('internalToken', $propNames);
    }

    // ── Attribute: Expose (name override) ────────────────────────────────────

    public function test_expose_attribute_overrides_property_name(): void
    {
        $schema = $this->parser->parse(new ReflectionClass(UserDto::class));
        $prop   = $this->findProperty($schema->properties(), 'full_name');

        $this->assertNotNull($prop, 'Expected a property named "full_name" from #[Expose(name: "full_name")]');
    }

    public function test_original_name_is_not_in_schema_when_expose_overrides(): void
    {
        $schema    = $this->parser->parse(new ReflectionClass(UserDto::class));
        $propNames = array_map(fn($p) => $p->name(), $schema->properties());

        $this->assertNotContains('name', $propNames);
        $this->assertContains('full_name', $propNames);
    }

    // ── Nested DTO refs ───────────────────────────────────────────────────────

    public function test_parses_nested_dto_as_ref_node(): void
    {
        $schema  = $this->parser->parse(new ReflectionClass(UserDto::class));
        $address = $this->findProperty($schema->properties(), 'address');

        $this->assertNotNull($address);
        $this->assertInstanceOf(RefTypeNode::class, $address->typeNode());
        $this->assertSame(AddressDto::class, $address->typeNode()->fqcn());
    }

    // ── Array of DTO refs ─────────────────────────────────────────────────────

    /**
     * UserDto::$previousAddresses is typed as `array` (untyped in PHP, typed in docblock).
     * Phase 1 maps bare `array` to ArrayTypeNode(ScalarTypeNode('mixed')).
     * Phase 2 will add docblock parsing for the @var annotation.
     */
    public function test_untyped_array_maps_to_unknown_item_array(): void
    {
        $schema    = $this->parser->parse(new ReflectionClass(UserDto::class));
        $addresses = $this->findProperty($schema->properties(), 'previousAddresses');

        $this->assertNotNull($addresses);
        $this->assertInstanceOf(ArrayTypeNode::class, $addresses->typeNode());
        $this->assertInstanceOf(ScalarTypeNode::class, $addresses->typeNode()->itemType());
    }

    // ── Readonly ──────────────────────────────────────────────────────────────

    public function test_promoted_readonly_params_are_marked_readonly(): void
    {
        $schema = $this->parser->parse(new ReflectionClass(CreateUserRequest::class));
        $email  = $this->findProperty($schema->properties(), 'email');

        $this->assertNotNull($email);
        $this->assertTrue($email->isReadonly());
    }

    // ── Multiple props ────────────────────────────────────────────────────────

    public function test_all_non_excluded_properties_are_present(): void
    {
        $schema    = $this->parser->parse(new ReflectionClass(UserDto::class));
        $propNames = array_map(fn($p) => $p->name(), $schema->properties());

        // internalToken is excluded; name is renamed to full_name
        $expected = ['id', 'email', 'full_name', 'active', 'address', 'previousAddresses', 'bio', 'avatarUrl'];

        $this->assertEqualsCanonicalizing($expected, $propNames);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * @param list<\PHPTypeS\Bridge\Schema\PropertySchema> $properties
     */
    private function findProperty(array $properties, string $name): ?\PHPTypeS\Bridge\Schema\PropertySchema
    {
        foreach ($properties as $prop) {
            if ($prop->name() === $name) {
                return $prop;
            }
        }

        return null;
    }
}
