<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;
use Tomaj\NetteApi\Params\CookieInputParam;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;

final class ParameterBuilderTest extends TestCase
{
    public function testOnlyQueryAndCookieParamsAreParameters(): void
    {
        $parameters = new ParameterBuilder()->build(new ConfigurableHandler([
            new GetInputParam('query'),
            new PostInputParam('post'),
            new FileInputParam('file'),
            new JsonInputParam('json', '{}'),
            new CookieInputParam('cookie'),
        ]));

        $this->assertSame([
            ['name' => 'query', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'cookie', 'in' => 'cookie', 'required' => false, 'schema' => ['type' => 'string']],
        ], $parameters);
    }

    public function testMultiRequiredParamWithExample(): void
    {
        $parameters = new ParameterBuilder()->build(new ConfigurableHandler([
            new GetInputParam('ids')->setMulti()->setRequired()->setDescription('Ids')->addExample('default', '5'),
        ]));

        $this->assertSame([[
            'name' => 'ids[]',
            'in' => 'query',
            'required' => true,
            'schema' => ['type' => 'array', 'items' => ['type' => 'string']],
            'description' => 'Ids',
            'example' => '5',
        ]], $parameters);
    }

    public function testAvailableValuesWithDescriptions(): void
    {
        $parameters = new ParameterBuilder()->build(new ConfigurableHandler([
            new GetInputParam('status')->setAvailableValues(['on' => 'Enabled', 'off' => 'Disabled'])->setDescription('Status')->setDefault('on'),
            new GetInputParam('order')->setAvailableValues(['asc', 'desc']),
        ]));

        $this->assertSame(['type' => 'string', 'enum' => ['on', 'off']], $parameters[0]['schema']);
        $this->assertSame("Status\n * `on` - Enabled\n * `off` - Disabled", $parameters[0]['description']);
        $this->assertSame('on', $parameters[0]['example']);

        $this->assertSame(['type' => 'string', 'enum' => ['asc', 'desc']], $parameters[1]['schema']);
        $this->assertArrayNotHasKey('description', $parameters[1]);
        $this->assertArrayNotHasKey('example', $parameters[1]);
    }

    public function testHandlerWithoutParams(): void
    {
        $this->assertSame([], new ParameterBuilder()->build(new ConfigurableHandler()));
    }
}
