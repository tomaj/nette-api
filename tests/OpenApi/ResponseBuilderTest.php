<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\OpenApi\ResponseBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Output\RedirectOutput;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;

final class ResponseBuilderTest extends TestCase
{
    public function testDefaultErrorResponses(): void
    {
        $builder = new ResponseBuilder();
        $bearer = new BearerTokenAuthorization(new StaticTokenRepository(), new StaticIpDetector('127.0.0.1'));

        $this->assertSame([500], array_keys($builder->build(new ConfigurableHandler(), new NoAuthorization(), false, new SchemaRegistry())));
        $this->assertSame([400, 500], array_keys($builder->build(new ConfigurableHandler(), new NoAuthorization(), true, new SchemaRegistry())));
        $this->assertSame([400, 401, 500], array_keys($builder->build(new ConfigurableHandler(), $bearer, true, new SchemaRegistry())));

        $responses = $builder->build(new ConfigurableHandler(), $bearer, true, new SchemaRegistry());
        $this->assertSame('#/components/schemas/ErrorWrongInput', $responses[400]['content']['application/json; charset=utf-8']['schema']['$ref']);
        $this->assertSame('#/components/schemas/ErrorUnauthorized', $responses[401]['content']['application/json; charset=utf-8']['schema']['$ref']);
        $this->assertSame('#/components/schemas/InternalServerError', $responses[500]['content']['application/json; charset=utf-8']['schema']['$ref']);
    }

    public function testJsonOutputWithExampleAndDefinitions(): void
    {
        $registry = new SchemaRegistry();
        $output = new JsonOutput(200, '{"$ref": "#/definitions/Item", "definitions": {"Item": {"type": ["integer", "null"]}}}', 'Item')->addExample('default', '5');

        $responses = new ResponseBuilder()->build(new ConfigurableHandler([], [$output]), new NoAuthorization(), false, $registry);

        $this->assertSame([
            'description' => 'Item',
            'content' => [
                'application/json; charset=utf-8' => [
                    'schema' => ['$ref' => '#/components/schemas/Item'],
                    'example' => 5,
                ],
            ],
        ], $responses[200]);
        $this->assertSame(['Item' => ['type' => 'integer', 'nullable' => true]], $registry->getDefinitions());
    }

    public function testJsonOutputWithMultipleExamples(): void
    {
        $output = new JsonOutput(200, '{"type": "object"}')->addExample('a', '{"x": 1}')->addExample('b', ['x' => 2]);
        $responses = new ResponseBuilder()->build(new ConfigurableHandler([], [$output]), new NoAuthorization(), false, new SchemaRegistry());

        $this->assertSame(['a' => ['x' => 1], 'b' => ['x' => 2]], $responses[200]['content']['application/json; charset=utf-8']['examples']);
    }

    public function testNonStringExamples(): void
    {
        $responses = new ResponseBuilder()->build(new ConfigurableHandler([], [
            new JsonOutput(200, '{"type": "integer"}')->addExample('default', 42),
            new JsonOutput(201, '{"type": "boolean"}')->addExample('yes', true)->addExample('no', false),
        ]), new NoAuthorization(), false, new SchemaRegistry());

        $this->assertSame(42, $responses[200]['content']['application/json; charset=utf-8']['example']);
        $this->assertSame(['yes' => true, 'no' => false], $responses[201]['content']['application/json; charset=utf-8']['examples']);
    }

    public function testMultipleOutputsWithSameCodeAreCombinedToOneOf(): void
    {
        $responses = new ResponseBuilder()->build(new ConfigurableHandler([], [
            new JsonOutput(200, '{"type": "object"}', 'First'),
            new JsonOutput(200, '{"type": "string"}'),
            new JsonOutput(200, '{"type": "integer"}'),
        ]), new NoAuthorization(), false, new SchemaRegistry());

        $this->assertSame('First', $responses[200]['description']);
        $this->assertSame(['oneOf' => [['type' => 'object'], ['type' => 'string'], ['type' => 'integer']]], $responses[200]['content']['application/json; charset=utf-8']['schema']);
    }

    public function testRedirectOutput(): void
    {
        $responses = new ResponseBuilder()->build(new ConfigurableHandler([], [new RedirectOutput(301, 'New location')]), new NoAuthorization(), false, new SchemaRegistry());

        $this->assertSame([
            'description' => 'Redirect',
            'headers' => [
                'Location' => [
                    'description' => 'New location',
                    'schema' => ['type' => 'string'],
                ],
            ],
        ], $responses[301]);
    }
}
