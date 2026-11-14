<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;
use Tomaj\NetteApi\OpenApi\PathBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Params\BodyInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\HeaderInputParam;
use Tomaj\NetteApi\Params\PathInputParam;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\UserDetailHandler;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;

final class TypedInputOpenApiTest extends TestCase
{
    public function testDtoOperationEqualsTheEquivalentParamsHandler(): void
    {
        $dto = $this->operation(new UserDetailHandler());
        $manual = $this->operation(new ConfigurableHandler([
            new PathInputParam('id')->setOpenApiSchema(['type' => 'integer', 'minimum' => 1]),
            new GetInputParam('limit')->setOpenApiSchema(['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]),
            new GetInputParam('status')->setOpenApiSchema(['type' => 'string', 'enum' => ['active', 'disabled']]),
            new HeaderInputParam('X-Request-Id')->setOpenApiSchema(['type' => 'string']),
        ]));

        $this->assertSame($manual['parameters'], $dto['parameters']);
        $this->assertMatchesSnapshot('typed-user-detail.json', $dto['parameters']);
    }

    public function testNestedBodyListsJsonAndForm(): void
    {
        $dto = $this->operation(new CreateOrderHandler());
        $schema = $dto['requestBody']['content']['application/json']['schema'];
        $manual = $this->operation(new ConfigurableHandler([
            new BodyInputParam('customerEmail')->setRequired()->setOpenApiSchema($schema['properties']['customerEmail'])->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
            new BodyInputParam('address')->setRequired()->setOpenApiSchema($schema['properties']['address'])->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
            new BodyInputParam('items')->setRequired()->setOpenApiSchema($schema['properties']['items'])->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
            new BodyInputParam('deliveryAt')->setOpenApiSchema($schema['properties']['deliveryAt'])->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
        ]));

        $this->assertSame($manual['requestBody'], $dto['requestBody']);
        $this->assertSame(['customerEmail', 'address', 'items'], $schema['required']);
        $this->assertSame('email', $schema['properties']['customerEmail']['format']);
        $this->assertSame(1, $schema['properties']['items']['minItems']);
        $this->assertArrayHasKey('application/x-www-form-urlencoded', $dto['requestBody']['content']);
        $this->assertMatchesSnapshot('typed-create-order.json', $dto['requestBody']);
    }

    public function testDirectPathAndHeaderParamsUseThoseLocations(): void
    {
        $parameters = new ParameterBuilder()->build(new ConfigurableHandler([
            new PathInputParam('id'),
            new HeaderInputParam('X-Request-Id')->setDescription('Request id'),
            new GetInputParam('page'),
        ]));

        $this->assertSame('path', $parameters[0]['in']);
        $this->assertTrue($parameters[0]['required']);
        $this->assertSame(['type' => 'string'], $parameters[0]['schema']);
        $this->assertSame('header', $parameters[1]['in']);
        $this->assertSame('Request id', $parameters[1]['description']);
        $this->assertSame('query', $parameters[2]['in']);
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(BaseHandler $handler): array
    {
        return new PathBuilder()->buildOperation(
            new Api(new EndpointIdentifier('GET', 1, 'sample'), $handler, new NoAuthorization()),
            new SchemaRegistry(),
        );
    }

    /**
     * @param array<mixed> $value
     */
    private function assertMatchesSnapshot(string $name, array $value): void
    {
        $actual = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $file = __DIR__ . '/snapshots/' . $name;
        if (getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($file, $actual);
            self::markTestIncomplete('Snapshot ' . $name . ' was written');
        }
        $this->assertFileExists($file);
        $this->assertSame(file_get_contents($file), $actual);
    }
}
