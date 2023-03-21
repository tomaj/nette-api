<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\OpenApiTransform;

class SchemaTransformerTest extends TestCase
{
    public function testSingeTypeRequest()
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

    public function testTypePropertyRequest()
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

    public function testNullStringRequest()
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

    public function testIntegerStringRequest()
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

    public function testNullIntegerStringRequest()
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

    public function testTypePropertyNullIntegerStringRequest()
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
