<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;

class SchemaRegistryTest extends TestCase
{
    public function testTransformMovesDefinitionsToComponents(): void
    {
        $registry = new SchemaRegistry();
        $schema = $registry->transform([
            'type' => 'object',
            'properties' => ['item' => ['$ref' => '#/definitions/Item']],
            'definitions' => [
                'Item' => ['type' => ['string', 'null']],
            ],
        ]);

        self::assertSame(['type' => 'object', 'properties' => ['item' => ['$ref' => '#/components/schemas/Item']]], $schema);
        self::assertSame(['Item' => ['type' => 'string', 'nullable' => true]], $registry->getDefinitions());
    }

    public function testNestedDefinitionsAreRegistered(): void
    {
        $registry = new SchemaRegistry();
        $registry->transform([
            '$ref' => '#/definitions/Outer',
            'definitions' => [
                'Outer' => [
                    '$ref' => '#/definitions/Inner',
                    'definitions' => ['Inner' => ['type' => 'integer']],
                ],
            ],
        ]);

        self::assertSame([
            'Inner' => ['type' => 'integer'],
            'Outer' => ['$ref' => '#/components/schemas/Inner'],
        ], $registry->getDefinitions());
    }

    public function testNumericDefinitionName(): void
    {
        $registry = new SchemaRegistry();
        $registry->transform(json_decode('{"$ref": "#/definitions/200", "definitions": {"200": {"type": "string"}}}', true));

        self::assertSame([200 => ['type' => 'string']], $registry->getDefinitions());
    }

    public function testSchemaThatCannotBeEncodedIsNull(): void
    {
        self::assertNull((new SchemaRegistry())->transform(['type' => 'number', 'example' => INF]));
    }

    public function testSameDefinitionCanBeAddedTwice(): void
    {
        $registry = new SchemaRegistry();
        $registry->add('Item', ['type' => 'string']);
        $registry->add('Item', ['type' => 'string']);

        self::assertSame(['Item' => ['type' => 'string']], $registry->getDefinitions());
    }

    public function testDifferentDefinitionWithSameNameThrowsException(): void
    {
        $registry = new SchemaRegistry();
        $registry->add('Item', ['type' => 'string']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Definition with name Item already exists. Rename it.');
        $registry->add('Item', ['type' => 'integer']);
    }
}
