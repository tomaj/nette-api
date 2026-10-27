<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\OpenApi\EndpointFilter;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;
use Tomaj\NetteApi\OpenApi\PathBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\UserListHandler;

class PathBuilderTest extends TestCase
{
    public function testBuildPaths(): void
    {
        $apis = [
            new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('POST', '1', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '1', 'orders', 'list'), new ConfigurableHandler(), new NoAuthorization()),
        ];

        $paths = (new PathBuilder())->build($apis, ApiFixtures::HOST_URL, '', ApiFixtures::apiLink(), new EndpointFilter(), new SchemaRegistry());

        self::assertSame(['/api/v1/users' => ['get', 'post'], '/api/v1/orders/list' => ['get']], array_map('array_keys', $paths));
    }

    public function testFilteredEndpointsAreSkipped(): void
    {
        $apis = [
            new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('POST', '1', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '1', 'orders'), new ConfigurableHandler(), new NoAuthorization()),
        ];

        $paths = (new PathBuilder())->build($apis, ApiFixtures::HOST_URL, '', ApiFixtures::apiLink(), new EndpointFilter(['users' => 'post']), new SchemaRegistry());

        self::assertSame(['/api/v1/users' => ['post']], array_map('array_keys', $paths));
    }

    public function testOnlyLeadingBasePathIsRemoved(): void
    {
        $apis = [
            new Api(new EndpointIdentifier('GET', '1', 'api-keys'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '1', 'users', 'api'), new ConfigurableHandler(), new NoAuthorization()),
        ];

        $paths = (new PathBuilder())->build($apis, ApiFixtures::HOST_URL, '/api', ApiFixtures::apiLink(), new EndpointFilter(), new SchemaRegistry());

        self::assertSame(['/v1/api-keys', '/v1/users', '/v1/users/api'], array_keys($paths));
    }

    public function testBasePath(): void
    {
        $apis = [
            new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '1', 'orders'), new ConfigurableHandler(), new NoAuthorization()),
        ];

        self::assertSame('', (new PathBuilder())->buildBasePath($apis, ApiFixtures::HOST_URL, ApiFixtures::apiLink()));
    }

    public function testOperation(): void
    {
        $authorization = new BearerTokenAuthorization(new StaticTokenRepository(), new StaticIpDetector('127.0.0.1'));
        $api = new Api(new EndpointIdentifier('GET', '1', 'users'), new UserListHandler(), $authorization);

        $operation = (new PathBuilder())->buildOperation($api, new SchemaRegistry());

        self::assertSame(['summary', 'description', 'tags', 'deprecated', 'parameters', 'security', 'responses'], array_keys($operation));
        self::assertSame('List users', $operation['summary']);
        self::assertSame('Returns paginated list of users', $operation['description']);
        self::assertSame(['users', 'listing'], $operation['tags']);
        self::assertTrue($operation['deprecated']);
        self::assertSame([['Bearer' => []]], $operation['security']);
        self::assertSame([400, 401, 500, 200, 206, 302], array_keys($operation['responses']));
    }

    public function testOperationWithoutInputHasNoBadRequestResponse(): void
    {
        $api = new Api(new EndpointIdentifier('GET', '1', 'ping'), new ConfigurableHandler(), new NoAuthorization());

        $operation = (new PathBuilder())->buildOperation($api, new SchemaRegistry());

        self::assertSame(['summary', 'description', 'tags', 'responses'], array_keys($operation));
        self::assertSame([500], array_keys($operation['responses']));
    }

    public function testOperationWithRequestBody(): void
    {
        $api = new Api(new EndpointIdentifier('POST', '1', 'users'), new ConfigurableHandler([new PostInputParam('name')]), new NoAuthorization());

        $operation = (new PathBuilder())->buildOperation($api, new SchemaRegistry());

        self::assertArrayHasKey('requestBody', $operation);
        self::assertArrayNotHasKey('parameters', $operation);
        self::assertSame([400, 500], array_keys($operation['responses']));
    }

    public function testCustomParameterBuilder(): void
    {
        $parameterBuilder = new class extends ParameterBuilder {
            public function build(ApiHandlerInterface $handler): array
            {
                $parameters = parent::build($handler);
                $parameters[] = ['name' => 'X-Request-Id', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string']];
                return $parameters;
            }
        };
        $api = new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler([new GetInputParam('id')]), new NoAuthorization());

        $operation = (new PathBuilder($parameterBuilder))->buildOperation($api, new SchemaRegistry());

        self::assertSame(['id', 'X-Request-Id'], array_column($operation['parameters'], 'name'));

        // bad request depends on handler params, not on parameters added by builder
        $operation = (new PathBuilder($parameterBuilder))->buildOperation(new Api(new EndpointIdentifier('GET', '1', 'ping'), new ConfigurableHandler(), new NoAuthorization()), new SchemaRegistry());
        self::assertSame(['X-Request-Id'], array_column($operation['parameters'], 'name'));
        self::assertSame([500], array_keys($operation['responses']));
    }
}
