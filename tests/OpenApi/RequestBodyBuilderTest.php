<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\OpenApi\RequestBodyBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Params\PutInputParam;
use Tomaj\NetteApi\Params\RawInputParam;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;

final class RequestBodyBuilderTest extends TestCase
{
    public function testNoBodyParams(): void
    {
        $this->assertNull(new RequestBodyBuilder()->build(new ConfigurableHandler([new GetInputParam('id')]), new SchemaRegistry()));
    }

    public function testJsonBodyRegistersDefinitions(): void
    {
        $registry = new SchemaRegistry();
        $param = new JsonInputParam('data', '{"$ref": "#/definitions/Item", "definitions": {"Item": {"type": "string"}}}')
            ->setRequired()
            ->setDescription('Data')
            ->addExample('default', '"abc"');

        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([$param]), $registry);

        $this->assertSame([
            'description' => 'Data',
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/Item', 'example' => 'abc'],
                ],
            ],
        ], $body);
        $this->assertSame(['Item' => ['type' => 'string']], $registry->getDefinitions());
    }

    public function testJsonBodyWithMultipleExamples(): void
    {
        $param = new JsonInputParam('data', '{"type": "object"}')->addExample('a', ['x' => 1])->addExample('b', '{"x": 2}');
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([$param]), new SchemaRegistry());

        $this->assertSame(['a' => ['x' => 1], 'b' => ['x' => 2]], $body['content']['application/json']['schema']['examples']);
    }

    public function testJsonBodyMultipleExamplesAreMergedWithSchemaExamples(): void
    {
        $param = new JsonInputParam('data', '{"type": "object", "examples": {"schema": {"x": 0}, "a": {"x": -1}}}')
            ->addExample('a', ['x' => 1])
            ->addExample('b', ['x' => 2]);
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([$param]), new SchemaRegistry());

        $this->assertSame(['schema' => ['x' => 0], 'a' => ['x' => 1], 'b' => ['x' => 2]], $body['content']['application/json']['schema']['examples']);
    }

    public function testJsonBodyWithNonStringExamples(): void
    {
        $single = new JsonInputParam('count', '{"type": "integer"}')->addExample('default', 5);
        $multiple = new JsonInputParam('flag', '{"type": "boolean"}')->addExample('yes', true)->addExample('no', false);

        $builder = new RequestBodyBuilder();
        $this->assertSame(5, $builder->build(new ConfigurableHandler([$single]), new SchemaRegistry())['content']['application/json']['schema']['example']);
        $this->assertSame(['yes' => true, 'no' => false], $builder->build(new ConfigurableHandler([$multiple]), new SchemaRegistry())['content']['application/json']['schema']['examples']);
    }

    public function testRawBody(): void
    {
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new RawInputParam('raw')->setDescription('Raw')->setRequired()->addExample('default', 'text'),
        ]), new SchemaRegistry());

        $this->assertSame([
            'description' => 'Raw',
            'required' => true,
            'content' => [
                'text/plain' => ['schema' => ['type' => 'string', 'example' => 'text']],
            ],
        ], $body);
    }

    public function testFormBody(): void
    {
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new PostInputParam('name')->setRequired()->addExample('default', 'John'),
            new PutInputParam('tags')->setMulti()->setAvailableValues(['a' => 'Tag A'])->setDefault('a'),
        ]), new SchemaRegistry());

        $this->assertSame([
            'required' => true,
            'content' => [
                'application/x-www-form-urlencoded' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'tags[]' => ['type' => 'array', 'items' => ['type' => 'string'], 'enum' => ['a'], 'description' => ' * `a` - Tag A'],
                        ],
                        'required' => ['name'],
                        'example' => ['name' => 'John', 'tags' => 'a'],
                    ],
                ],
            ],
        ], $body);
    }

    public function testFilesSwitchToMultipart(): void
    {
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new PostInputParam('name'),
            new FileInputParam('photo')->setRequired()->setDescription('Photo'),
            new FileInputParam('docs')->setMulti(),
        ]), new SchemaRegistry());

        $this->assertSame(['multipart/form-data'], array_keys($body['content']));
        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'photo' => ['type' => 'string', 'description' => 'Photo', 'format' => 'binary'],
                'docs[]' => ['type' => 'array', 'description' => '', 'items' => ['type' => 'string', 'format' => 'binary']],
            ],
            'required' => ['photo'],
        ], $body['content']['multipart/form-data']['schema']);
    }

    public function testRawBodyKeepsDescriptionAndRequiredOfEarlierParam(): void
    {
        $json = new JsonInputParam('data', '{"type": "object"}')->setRequired()->setDescription('Json');
        $raw = new RawInputParam('raw')->setDescription('Raw');

        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([$json, $raw]), new SchemaRegistry());

        $this->assertIsArray($body);
        $this->assertSame('Json', $body['description']);
        $this->assertTrue($body['required']);
    }
}
