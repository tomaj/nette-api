<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;
use Tomaj\NetteApi\Params\CookieInputParam;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class ParameterBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        RequestInput::reset();
        $_GET = [];
    }

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

    public function testAMultiParamWithAnExplicitNonArraySchemaKeepsItsPlainName(): void
    {
        $parameters = new ParameterBuilder()->build(new ConfigurableHandler([
            new GetInputParam('csv')->setMulti()->setOpenApiSchema(['type' => 'string']),
            new GetInputParam('ids')->setMulti()->setOpenApiSchema(['type' => 'array', 'items' => ['type' => 'integer']]),
        ]));

        $this->assertSame(['csv', 'ids[]'], array_column($parameters, 'name'));
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

    public function testQueryListIsAdvertisedInTheFormPhpReads(): void
    {
        $parameters = new ParameterBuilder()->build(new QueryListHandler());
        $this->assertSame(['ids[]', 'csv'], array_column($parameters, 'name'));
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $parameters[0]['schema']);

        $name = rawurlencode((string) $parameters[0]['name']);
        $query = sprintf('%s=3&%s=4&csv=a,b', $name, $name);
        parse_str($query, $_GET);
        $this->assertSame(['ids' => [3, 4], 'csv' => ['a', 'b']], $this->payload(new QueryListHandler()));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TypedHandler $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError(), (string) json_encode($processor->getErrors()));

        return $handler->handle($processor->getValues())->getPayload();
    }
}

final readonly class QueryListInput
{
    /**
     * @param list<int> $ids
     * @param list<string> $csv
     */
    public function __construct(
        #[Query] public array $ids,
        #[Query] #[Cast(Split::class, ',')] public array $csv,
    ) {
    }
}

final class QueryListHandler extends TypedHandler
{
    public function handleDto(QueryListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['ids' => $input->ids, 'csv' => $input->csv]);
    }
}
