<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\OpenApi\RequestBodyBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Params\BodyInputParam;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Params\PutInputParam;
use Tomaj\NetteApi\Params\RawInputParam;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\DescribedHandler;

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

    public function testBodyParamWithoutSchemaIsDocumentedAsString(): void
    {
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new BodyInputParam('name')->setRequired()->setDescription('Name'),
            new BodyInputParam('tags')->setMulti(),
        ]), new SchemaRegistry());

        $this->assertIsArray($body);
        $this->assertTrue($body['required']);
        $this->assertSame(['application/json', 'application/x-www-form-urlencoded'], array_keys($body['content']));
        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Name'],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['name'],
        ], $body['content']['application/json']['schema']);
        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Name'],
                'tags[]' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['name'],
        ], $body['content']['application/x-www-form-urlencoded']['schema']);
    }

    public function testBodyWithOptionalFileFallsBackToJsonAndMultipart(): void
    {
        $file = ['type' => 'string', 'format' => 'binary'];
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new BodyInputParam('name')->setRequired(),
            new FileInputParam('photo')->setOpenApiSchema($file),
        ]), new SchemaRegistry());

        $this->assertIsArray($body);
        $this->assertSame(['application/json', 'multipart/form-data'], array_keys($body['content']));
        $this->assertArrayNotHasKey('photo', $body['content']['application/json']['schema']['properties']);
        $this->assertSame($file, $body['content']['multipart/form-data']['schema']['properties']['photo']);
    }

    public function testBodyParamsShareTheFormWithSchemalessFileParams(): void
    {
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new FileInputParam('photo')->setRequired(),
            new BodyInputParam('name')->setRequired(),
        ]), new SchemaRegistry());

        $this->assertIsArray($body);
        $this->assertSame(['multipart/form-data', 'application/json'], array_keys($body['content']));
        $this->assertSame(['photo', 'name'], array_keys($body['content']['multipart/form-data']['schema']['properties']));
        $this->assertSame(['photo', 'name'], $body['content']['multipart/form-data']['schema']['required']);
    }

    public function testFormSchemaNamesListsEncodesObjectsAndDropsNull(): void
    {
        $address = [
            'type' => 'object',
            'properties' => ['street' => ['type' => 'string', 'nullable' => true]],
            'required' => ['street'],
        ];
        $types = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'];
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new BodyInputParam('tags')->setRequired()->setOpenApiSchema(['type' => 'array', 'items' => ['type' => 'string', 'nullable' => true]])->setContentTypes($types),
            new BodyInputParam('address')->setOpenApiSchema($address)->setContentTypes($types),
            new BodyInputParam('status')->setOpenApiSchema(['type' => 'string', 'enum' => [null, 'a', 'b'], 'nullable' => true])->setContentTypes($types),
        ]), new SchemaRegistry());

        $this->assertIsArray($body);
        $this->assertSame($types, array_keys($body['content']));
        $this->assertSame(['tags', 'address', 'status'], array_keys($body['content']['application/json']['schema']['properties']));
        $this->assertTrue($body['content']['application/json']['schema']['properties']['status']['nullable']);
        $this->assertArrayNotHasKey('encoding', $body['content']['application/json']);
        $this->assertSame([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'tags[]' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'address' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string']], 'required' => ['street']],
                    'status' => ['type' => 'string', 'enum' => ['a', 'b']],
                ],
                'required' => ['tags[]'],
            ],
            'encoding' => ['address' => ['style' => 'deepObject', 'explode' => true]],
        ], $body['content']['application/x-www-form-urlencoded']);
        // encoding styles apply only to urlencoded forms, so multipart lists the fields of the object; an optional object has no required field
        $this->assertSame([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'tags[]' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'address[street]' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['a', 'b']],
                ],
                'required' => ['tags[]'],
            ],
        ], $body['content']['multipart/form-data']);
    }

    public function testBodyParamsJoinTheFormOfPostParams(): void
    {
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new PostInputParam('name')->setRequired(),
            new PostInputParam('note')->setRequired(),
            new BodyInputParam('name')->setRequired(),
            new BodyInputParam('age')->setRequired(),
            new BodyInputParam('city')->setRequired(),
        ]), new SchemaRegistry());

        $this->assertIsArray($body);
        $this->assertSame(['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'note' => ['type' => 'string'], 'age' => ['type' => 'string'], 'city' => ['type' => 'string']], 'required' => ['name', 'note', 'age', 'city']], $body['content']['application/x-www-form-urlencoded']['schema']);
    }

    public function testOptionalBodyParamsKeepTheBodyOfPostParamsRequired(): void
    {
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new PostInputParam('note'),
            new BodyInputParam('name'),
        ]), new SchemaRegistry());

        $this->assertIsArray($body);
        $this->assertTrue($body['required']);
    }

    public function testBodyParamWithLabelsOnlyDescribesLabelsThatDifferFromValues(): void
    {
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new BodyInputParam('same')->setAvailableValues(['a' => 'a', 'b' => 'b']),
            new BodyInputParam('labelled')->setAvailableValues(['a' => 'Tag A']),
        ]), new SchemaRegistry());

        $this->assertIsArray($body);
        $properties = $body['content']['application/json']['schema']['properties'];
        $this->assertSame(['type' => 'string', 'enum' => ['a', 'b']], $properties['same']);
        $this->assertSame(['type' => 'string', 'enum' => ['a'], 'description' => ' * `a` - Tag A'], $properties['labelled']);
    }

    public function testListOfObjectsIsDocumentedOnlyAsJson(): void
    {
        $items = ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['sku' => ['type' => 'string']]]];
        $nested = ['type' => 'object', 'properties' => ['geo' => ['type' => 'object', 'properties' => []]]];
        $types = ['application/json', 'application/x-www-form-urlencoded'];
        foreach ([$items, $nested] as $schema) {
            $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
                new BodyInputParam('value')->setOpenApiSchema($schema)->setContentTypes($types),
            ]), new SchemaRegistry());
            $this->assertIsArray($body);
            $this->assertSame(['application/json'], array_keys($body['content']));
        }

        // when only form media types are accepted, they are still documented (best effort) instead of an empty body
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new BodyInputParam('items')->setOpenApiSchema($items)->setContentTypes(['application/x-www-form-urlencoded']),
        ]), new SchemaRegistry());
        $this->assertIsArray($body);
        $this->assertSame(['application/x-www-form-urlencoded'], array_keys($body['content']));
        $form = $body['content']['application/x-www-form-urlencoded'];
        $this->assertSame(['items'], array_keys($form['schema']['properties']), 'a list of objects is not a list of scalars, so it is not named items[]');
        $this->assertSame("Sent as form fields in bracket notation (`items[...]`), which OpenAPI 3.0 cannot describe.", $form['schema']['properties']['items']['description']);
        $this->assertArrayNotHasKey('encoding', $form);
    }

    public function testAFileStaysDocumentedNextToAListOfObjects(): void
    {
        $items = ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['sku' => ['type' => 'string']]], 'description' => 'Order lines'];
        $body = new RequestBodyBuilder()->build(new ConfigurableHandler([
            new BodyInputParam('items')->setOpenApiSchema($items)->setRequired()->setContentTypes(['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data']),
            new FileInputParam('attachment')->setOpenApiSchema(['type' => 'string', 'format' => 'binary']),
            new BodyInputParam('note'),
        ]), new SchemaRegistry());

        $this->assertIsArray($body);
        $this->assertSame(['application/json', 'multipart/form-data'], array_keys($body['content']), 'multipart is the only way to send the file');
        $this->assertSame(['items', 'note'], array_keys($body['content']['application/json']['schema']['properties']));
        $multipart = $body['content']['multipart/form-data']['schema'];
        $this->assertSame(['items', 'attachment', 'note'], array_keys($multipart['properties']));
        $this->assertSame(['items'], $multipart['required']);
        $this->assertSame("Order lines\n\nSent as form fields in bracket notation (`items[...]`), which OpenAPI 3.0 cannot describe.", $multipart['properties']['items']['description']);
    }

    public function testOpenApiUsesTheDeclaredBodyMediaTypeAndDescription(): void
    {
        $builder = new RequestBodyBuilder();
        $body = $builder->build(new DescribedHandler(), new SchemaRegistry());
        $this->assertIsArray($body);
        $this->assertSame(['application/json'], array_keys($body['content']));
        $this->assertSame('Customer email', $body['content']['application/json']['schema']['properties']['email']['description']);
        $this->assertFalse($body['required']);
    }
}
