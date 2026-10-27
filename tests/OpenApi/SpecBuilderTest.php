<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\OpenApi\PathBuilder;
use Tomaj\NetteApi\OpenApi\ResponseBuilder;
use Tomaj\NetteApi\OpenApi\SecuritySchemeBuilder;
use Tomaj\NetteApi\OpenApi\SpecBuilder;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;

final class SpecBuilderTest extends TestCase
{
    private function createSpecBuilder(): SpecBuilder
    {
        return new SpecBuilder(new PathBuilder());
    }

    public function testOnlyApisOfVersionAreIncluded(): void
    {
        $apis = [
            new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '2', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '2', 'orders'), new ConfigurableHandler(), new NoAuthorization()),
        ];

        $spec = $this->createSpecBuilder()->build($apis, '2', ApiFixtures::HOST_URL, ApiFixtures::apiLink());

        $this->assertSame(['openapi', 'info', 'servers', 'components', 'paths'], array_keys($spec));
        $this->assertSame('3.0.0', $spec['openapi']);
        $this->assertSame(['version' => '2', 'title' => 'Nette API'], $spec['info']);
        $this->assertSame([['url' => ApiFixtures::HOST_URL]], $spec['servers']);
        $this->assertSame(['/api/v2/users', '/api/v2/orders'], array_keys($spec['paths']));
    }

    public function testComponents(): void
    {
        $apis = [
            new Api(
                new EndpointIdentifier('GET', '1', 'users'),
                new ConfigurableHandler([], [new JsonOutput(200, '{"$ref": "#/definitions/User", "definitions": {"User": {"type": "object"}}}')]),
                new BearerTokenAuthorization(new StaticTokenRepository(), new StaticIpDetector('127.0.0.1'))
            ),
        ];

        $spec = $this->createSpecBuilder()->build($apis, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink());

        $this->assertSame(['Bearer' => ['type' => 'http', 'scheme' => 'bearer']], $spec['components']['securitySchemes']);
        $this->assertSame(['User', 'ErrorWrongInput', 'ErrorUnauthorized', 'InternalServerError'], array_keys($spec['components']['schemas']));
    }

    public function testWithoutSecuritySchemes(): void
    {
        $apis = [new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler(), new NoAuthorization())];

        $spec = $this->createSpecBuilder()->build($apis, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink());

        $this->assertSame(['schemas'], array_keys($spec['components']));
    }

    public function testInitDataAndAllowedEndpoints(): void
    {
        $apis = [
            new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '1', 'orders'), new ConfigurableHandler(), new NoAuthorization()),
        ];

        $spec = $this->createSpecBuilder()->build($apis, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink(), ['info' => ['title' => 'My API']], ['orders']);

        $this->assertSame(['version' => '1', 'title' => 'My API'], $spec['info']);
        $this->assertSame(['/api/v1/orders'], array_keys($spec['paths']));
    }

    public function testCustomSecuritySchemeBuilderIsUsedForComponentsAndOperations(): void
    {
        $securitySchemeBuilder = new class extends SecuritySchemeBuilder {
            protected function createScheme(ApiAuthorizationInterface $authorization): array
            {
                $scheme = parent::createScheme($authorization);
                return isset($scheme['Bearer']) ? ['JWT' => $scheme['Bearer'] + ['bearerFormat' => 'JWT']] : $scheme;
            }
        };
        $apis = [
            new Api(new EndpointIdentifier('GET', '1', 'users'), new ConfigurableHandler(), new BearerTokenAuthorization(new StaticTokenRepository(), new StaticIpDetector('127.0.0.1'))),
        ];

        $specBuilder = new SpecBuilder(new PathBuilder(securitySchemeBuilder: $securitySchemeBuilder));
        $spec = $specBuilder->build($apis, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink());

        $this->assertSame(['JWT' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']], $spec['components']['securitySchemes']);
        $this->assertSame([['JWT' => []]], $spec['paths']['/api/v1/users']['get']['security']);
    }

    public function testNumericDefinitionNameIsKeptInComponents(): void
    {
        $apis = [
            new Api(new EndpointIdentifier('GET', '1', 'a'), new ConfigurableHandler([], [new JsonOutput(200, '{"$ref": "#/definitions/200", "definitions": {"200": {"type": "string"}}}')]), new NoAuthorization()),
        ];

        $spec = $this->createSpecBuilder()->build($apis, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink());

        $this->assertSame([200, 'ErrorWrongInput', 'ErrorUnauthorized', 'InternalServerError'], array_keys($spec['components']['schemas']));
        $this->assertSame('#/components/schemas/200', $spec['paths']['/api/v1/a']['get']['responses'][200]['content']['application/json; charset=utf-8']['schema']['$ref']);
    }

    public function testVersionZero(): void
    {
        $apis = [new Api(new EndpointIdentifier('GET', '0', 'users'), new ConfigurableHandler(), new NoAuthorization())];

        $spec = $this->createSpecBuilder()->build($apis, '0', ApiFixtures::HOST_URL, ApiFixtures::apiLink());

        $this->assertSame('0', $spec['info']['version']);
        $this->assertSame(['/api/v0/users'], array_keys($spec['paths']));
    }

    public function testSchemaThatCannotBeEncodedDoesNotBreakSpec(): void
    {
        $param = new JsonInputParam('data', '{"type": "number"}')->addExample('default', INF);
        $apis = [
            new Api(new EndpointIdentifier('POST', '1', 'a'), new ConfigurableHandler([$param]), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '1', 'b'), new ConfigurableHandler(), new NoAuthorization()),
        ];

        $spec = $this->createSpecBuilder()->build($apis, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink());

        $this->assertNull($spec['paths']['/api/v1/a']['post']['requestBody']['content']['application/json']['schema']);
        $this->assertArrayHasKey('/api/v1/b', $spec['paths']);
    }

    public function testCustomErrorSchemas(): void
    {
        $responseBuilder = new class extends ResponseBuilder {
            public function buildErrorSchemas(): array
            {
                return ['InternalServerError' => ['type' => 'object']] + parent::buildErrorSchemas();
            }
        };
        $apis = [new Api(new EndpointIdentifier('GET', '1', 'a'), new ConfigurableHandler(), new NoAuthorization())];

        $spec = new SpecBuilder(new PathBuilder(responseBuilder: $responseBuilder))->build($apis, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink());

        $this->assertSame(['type' => 'object'], $spec['components']['schemas']['InternalServerError']);
    }

    public function testBuildersCanBeCreatedWithoutArguments(): void
    {
        foreach ([SpecBuilder::class, PathBuilder::class] as $class) {
            foreach (new \ReflectionMethod($class, '__construct')->getParameters() as $parameter) {
                // older nette/di can autowire missing services only for nullable parameters
                $this->assertTrue($parameter->allowsNull(), $class . '::$' . $parameter->getName() . ' must be nullable');
            }
        }
    }

    public function testDefinitionsAreNotSharedBetweenBuilds(): void
    {
        $builder = $this->createSpecBuilder();
        $first = [new Api(new EndpointIdentifier('GET', '1', 'a'), new ConfigurableHandler([], [new JsonOutput(200, '{"definitions": {"Item": {"type": "string"}}}')]), new NoAuthorization())];
        $second = [new Api(new EndpointIdentifier('GET', '2', 'a'), new ConfigurableHandler([], [new JsonOutput(200, '{"definitions": {"Item": {"type": "integer"}}}')]), new NoAuthorization())];

        $this->assertSame(['type' => 'string'], $builder->build($first, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink())['components']['schemas']['Item']);
        $this->assertSame(['type' => 'integer'], $builder->build($second, '2', ApiFixtures::HOST_URL, ApiFixtures::apiLink())['components']['schemas']['Item']);
    }
}
