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

class ParameterBuilderTest extends TestCase
{
    public function testOnlyQueryAndCookieParamsAreParameters(): void
    {
        $parameters = (new ParameterBuilder())->build(new ConfigurableHandler([
            new GetInputParam('query'),
            new PostInputParam('post'),
            new FileInputParam('file'),
            new JsonInputParam('json', '{}'),
            new CookieInputParam('cookie'),
        ]));

        self::assertSame([
            ['name' => 'query', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
            ['name' => 'cookie', 'in' => 'cookie', 'required' => false, 'schema' => ['type' => 'string']],
        ], $parameters);
    }

    public function testMultiRequiredParamWithExample(): void
    {
        $parameters = (new ParameterBuilder())->build(new ConfigurableHandler([
            (new GetInputParam('ids'))->setMulti()->setRequired()->setDescription('Ids')->setExample('5'),
        ]));

        self::assertSame([[
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
        $parameters = (new ParameterBuilder())->build(new ConfigurableHandler([
            (new GetInputParam('status'))->setAvailableValues(['on' => 'Enabled', 'off' => 'Disabled'])->setDescription('Status')->setDefault('on'),
            (new GetInputParam('order'))->setAvailableValues(['asc', 'desc']),
        ]));

        self::assertSame(['type' => 'string', 'enum' => ['on', 'off']], $parameters[0]['schema']);
        self::assertSame("Status\n * `on` - Enabled\n * `off` - Disabled", $parameters[0]['description']);
        self::assertSame('on', $parameters[0]['example']);

        self::assertSame(['type' => 'string', 'enum' => ['asc', 'desc']], $parameters[1]['schema']);
        self::assertArrayNotHasKey('description', $parameters[1]);
        self::assertArrayNotHasKey('example', $parameters[1]);
    }

    public function testHandlerWithoutParams(): void
    {
        self::assertSame([], (new ParameterBuilder())->build(new ConfigurableHandler()));
    }
}
