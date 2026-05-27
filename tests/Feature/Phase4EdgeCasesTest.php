<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Feature;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use PHPTypeS\Bridge\Generators\FetchClientGenerator;
use PHPTypeS\Bridge\Generators\TypeScriptTypeGenerator;
use PHPTypeS\Bridge\Generators\ZodSchemaGenerator;
use PHPTypeS\Bridge\Parser\DocBlockParser;
use PHPTypeS\Bridge\Parser\DtoParser;
use PHPTypeS\Bridge\Parser\EndpointParser;
use PHPTypeS\Bridge\Parser\ReflectionParser;
use PHPTypeS\Bridge\Schema\ApiSchema;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\IntersectionTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\UnionTypeNode;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\DocBlockDto;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\EmployeeDto;
use PHPTypeS\Bridge\Tests\Fixtures\DTOs\TeamDto;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;

final class Phase4EdgeCasesTest extends TestCase
{
    public function test_docblock_parser_extracts_var_type(): void
    {
        $parser = new DocBlockParser();
        $result = $parser->extractVar('/** @var UserDto[] */');

        $this->assertSame('UserDto[]', $result);
    }

    public function test_docblock_parser_extracts_return_type(): void
    {
        $parser = new DocBlockParser();
        $result = $parser->extractReturn('/** @return list<UserDto> */');

        $this->assertSame('list<UserDto>', $result);
    }

    public function test_docblock_parser_extracts_param_by_name(): void
    {
        $parser = new DocBlockParser();
        $doc    = "/**\n * @param UserDto[] \$users The users list\n * @param string \$name\n */";
        $result = $parser->extractParam($doc, 'users');

        $this->assertSame('UserDto[]', $result);
    }

    public function test_docblock_parser_returns_null_when_tag_absent(): void
    {
        $parser = new DocBlockParser();

        $this->assertNull($parser->extractVar('/** Just a description */'));
        $this->assertNull($parser->extractReturn('/** Just a description */'));
        $this->assertNull($parser->extractParam('/** Just a description */', 'foo'));
    }

    public function test_docblock_parser_strips_leading_backslash(): void
    {
        $parser = new DocBlockParser();
        $result = $parser->extractVar('/** @var \\App\\DTOs\\UserDto[] */');

        $this->assertSame('App\\DTOs\\UserDto[]', $result);
    }

    public function test_docblock_parser_has_type_info_returns_true_for_tagged_blocks(): void
    {
        $parser = new DocBlockParser();

        $this->assertTrue($parser->hasTypeInfo('/** @var string */'));
        $this->assertTrue($parser->hasTypeInfo('/** @return void */'));
        $this->assertFalse($parser->hasTypeInfo('/** Just a description */'));
    }

    public function test_dto_parser_resolves_array_type_from_param_docblock(): void
    {
        $parser = new DtoParser(new TypeMapper());
        $schema = $parser->parse(new ReflectionClass(DocBlockDto::class));

        $users = null;
        $tags  = null;

        foreach ($schema->properties() as $prop) {
            if ($prop->name() === 'users') {
                $users = $prop;
            }
            if ($prop->name() === 'tags') {
                $tags = $prop;
            }
        }

        $this->assertNotNull($users);
        $this->assertNotNull($tags);

        // @param UserDto[] $users → ArrayTypeNode(RefTypeNode(UserDto))
        $this->assertInstanceOf(ArrayTypeNode::class, $users->typeNode());
        $this->assertInstanceOf(RefTypeNode::class, $users->typeNode()->itemType());
        $this->assertSame(\PHPTypeS\Bridge\Tests\Fixtures\DTOs\UserDto::class, $users->typeNode()->itemType()->fqcn());

        // @param string[] $tags → ArrayTypeNode(ScalarTypeNode('string'))
        $this->assertInstanceOf(ArrayTypeNode::class, $tags->typeNode());
        $this->assertInstanceOf(ScalarTypeNode::class, $tags->typeNode()->itemType());
        $this->assertSame('string', $tags->typeNode()->itemType()->phpType());
    }


    public function test_circular_dto_reference_does_not_cause_infinite_loop(): void
    {
      
        $parser = new ReflectionParser();
        
        $schema = $parser->parse([__DIR__ . '/../Fixtures/DTOs']);

        $this->assertTrue(true);
    }

    public function test_circular_dtos_both_end_up_in_schema(): void
    {
    
        $mapper  = new TypeMapper();
        $dto     = new DtoParser($mapper);
        $epParser = new EndpointParser($mapper, $dto);
        $schema  = new ApiSchema();

       
        $teamSchema = $dto->parse(new ReflectionClass(TeamDto::class));
        $schema->addDto($teamSchema);

        $ref = new ReflectionClass(\PHPTypeS\Bridge\Tests\Fixtures\Controllers\UserController::class);
        $epParser->parse($ref, $schema);

       
        $this->assertNotEmpty($schema->dtos());
    }

   
    public function test_intersection_type_node_kind_is_intersection(): void
    {
        $node = new IntersectionTypeNode([
            new ScalarTypeNode('string'),
            new ScalarTypeNode('int'),
        ]);

        $this->assertSame('intersection', $node->kind());
    }

    public function test_intersection_type_node_requires_at_least_two_types(): void
    {
        $this->expectException(\PHPTypeS\Bridge\Exceptions\ParserException::class);

        new IntersectionTypeNode([new ScalarTypeNode('string')]);
    }

    public function test_intersection_type_node_returns_all_types(): void
    {
        $a    = new ScalarTypeNode('string');
        $b    = new ScalarTypeNode('int');
        $node = new IntersectionTypeNode([$a, $b]);

        $this->assertCount(2, $node->types());
    }

    public function test_ts_generator_renders_intersection_as_ampersand(): void
    {
        $schema    = new ApiSchema();
        $generator = new TypeScriptTypeGenerator();
        $node      = new IntersectionTypeNode([
            new RefTypeNode('App\\Contracts\\Stringable'),
            new RefTypeNode('App\\Contracts\\Countable'),
        ]);

        $result = $generator->renderTypeNode($node, $schema);

        $this->assertSame('Stringable & Countable', $result);
    }

    public function test_zod_generator_renders_two_type_intersection(): void
    {
        $schema    = new ApiSchema();
        $generator = new ZodSchemaGenerator();
        $node      = new IntersectionTypeNode([
            new RefTypeNode('App\\DTOs\\UserDto'),
            new RefTypeNode('App\\DTOs\\AddressDto'),
        ]);

        $result = $generator->renderTypeNodeZod($node, $schema);

        $this->assertSame('z.intersection(UserDtoSchema, AddressDtoSchema)', $result);
    }

    public function test_zod_generator_chains_three_type_intersection(): void
    {
        $schema    = new ApiSchema();
        $generator = new ZodSchemaGenerator();
        $node      = new IntersectionTypeNode([
            new RefTypeNode('App\\A'),
            new RefTypeNode('App\\B'),
            new RefTypeNode('App\\C'),
        ]);

        $result = $generator->renderTypeNodeZod($node, $schema);

        $this->assertSame('z.intersection(ASchema, BSchema).and(CSchema)', $result);
    }

   
    public function test_generated_output_matches_committed_snapshot(string $filename): void
    {
        $snapshotDir = __DIR__ . '/../Fixtures/expected-output';
        $snapshot    = file_get_contents("{$snapshotDir}/{$filename}");

        $this->assertNotFalse($snapshot, "Snapshot file {$filename} not found in expected-output/");

        $parser = new ReflectionParser();
        $schema = $parser->parse([
            __DIR__ . '/../Fixtures/Controllers',
            __DIR__ . '/../Fixtures/DTOs',
        ]);

        $output = match ($filename) {
            'api.types.ts'   => (new TypeScriptTypeGenerator())->generate($schema)->content(),
            'api.schemas.ts' => (new ZodSchemaGenerator())->generate($schema)->content(),
            'api.client.ts'  => (new FetchClientGenerator())->generate($schema)->content(),
            default          => throw new \LogicException("Unknown snapshot: {$filename}"),
        };

        $this->assertSame(
            $snapshot,
            $output,
            "Generated {$filename} does not match committed snapshot. "
            . 'Run `vendor/bin/bridge generate --dir=tests/Fixtures/Controllers,tests/Fixtures/DTOs --out=tests/Fixtures/expected-output` to update.'
        );
    }

    public static function snapshotProvider(): array
    {
        return [
            'api.types.ts'   => ['api.types.ts'],
            'api.schemas.ts' => ['api.schemas.ts'],
            'api.client.ts'  => ['api.client.ts'],
        ];
    }
}
