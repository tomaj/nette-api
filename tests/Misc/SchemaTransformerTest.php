<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\OpenApiTransform;

final class SchemaTransformerTest extends TestCase
{
    public function testSingeTypeRequest(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'image' => ['type' => 'string']
            ]
        ];
        $expected = [
            'type' => 'object',
            'properties' => [
                'image' => [
                    'type' => 'string'
                ]
            ]
        ];

        OpenApiTransform::transformTypes($schema);
        $this->assertEquals($expected, $schema);
    }

    public function testTypePropertyRequest(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string']
            ]
        ];
        $expected = [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string'
                ]
            ]
        ];

        OpenApiTransform::transformTypes($schema);
        $this->assertEquals($expected, $schema);
    }

    public function testNullStringRequest(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'image' => ['type' => ['string', 'null']],
            ]
        ];
        $expected = [
            'type' => 'object',
            'properties' => [
                'image' => [
                    'type' => 'string',
                    'nullable' => true
                ]
            ]
        ];

        OpenApiTransform::transformTypes($schema);
        $this->assertEquals($expected, $schema);
    }

    public function testIntegerStringRequest(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'image' => ['type' => ['string', 'integer']],
            ]
        ];
        $expected = [
            'type' => 'object',
            'properties' => [
                'image' => [
                    'oneOf' => [
                        ['type' => 'string'],
                        ['type' => 'integer'],
                    ],
                ]
            ]
        ];

        OpenApiTransform::transformTypes($schema);
        $this->assertEquals($expected, $schema);
    }

    public function testNullIntegerStringRequest(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'image' => ['type' => ['string', 'integer', 'null']],
            ]
        ];
        $expected = [
            'type' => 'object',
            'properties' => [
                'image' => [
                    'oneOf' => [
                        ['type' => 'string'],
                        ['type' => 'integer'],
                    ],
                    'nullable' => true
                ]
            ]
        ];

        OpenApiTransform::transformTypes($schema);
        $this->assertEquals($expected, $schema);
    }

    public function testTypePropertyNullIntegerStringRequest(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => ['string', 'integer', 'null']],
            ]
        ];
        $expected = [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'oneOf' => [
                        ['type' => 'string'],
                        ['type' => 'integer'],
                    ],
                    'nullable' => true
                ]
            ]
        ];

        OpenApiTransform::transformTypes($schema);
        $this->assertEquals($expected, $schema);
    }
}
