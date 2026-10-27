<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use InvalidArgumentException;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\DI\Container;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Handlers\OpenApiHandler;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Nette\Http\Request;

final class OpenApiHandlerTest extends TestCase
{
    /** @var Container */
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testHandlerWithMultipleResponseSchemas(): void
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);
        $request = new Request(new UrlScript('http://test/'));

        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'test'),
            new MultipleOutputTestHandler(),
            new NoAuthorization()
        );

        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'docs', 'open-api'),
            new OpenApiHandler($apiDecider, $apiLink, $request),
            new NoAuthorization()
        );

        $result = $apiDecider->getApi('GET', '1', 'docs', 'open-api');
        $handler = $result->getHandler();

        $response = $handler->handle(['format' => 'json']);
        $this->assertSame(200, $response->getCode());
        $payload = $response->getPayload();

        $this->assertEquals('string', $payload['components']['schemas']['ErrorWrongInput']['properties']['message']['type']);
        $this->assertArrayNotHasKey('enum', $payload['components']['schemas']['ErrorWrongInput']['properties']['message']);
        $this->assertCount(2, $payload['paths']);

        $def = array_values($payload['paths'])[0]; // MultipleOutputTestHandler
        $this->assertCount(2, $def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema']['oneOf']);

        $def = array_values($payload['paths'])[1]; // OpenApiHandler
        $this->assertArrayNotHasKey('oneOf', $def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema'] ?? []);
    }

    public function testConflictingDefinitionsThrowException(): void
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'first'), $this->createHandlerWithDefinition('{"type": "string"}'), new NoAuthorization());
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'second'), $this->createHandlerWithDefinition('{"type": "integer"}'), new NoAuthorization());
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'docs', 'open-api'),
            new OpenApiHandler($apiDecider, new ApiLink($linkGenerator), new Request(new UrlScript('http://test/'))),
            new NoAuthorization()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Definition with name Item already exists. Rename it.');
        $apiDecider->getApi('GET', '1', 'docs', 'open-api')->getHandler()->handle(['format' => 'json']);
    }

    private function createHandlerWithDefinition(string $definition): BaseHandler
    {
        return new class ($definition) extends BaseHandler {
            public function __construct(private readonly string $definition)
            {
                parent::__construct();
            }

            public function outputs(): array
            {
                return [new JsonOutput(200, '{"$ref": "#/definitions/Item", "definitions": {"Item": ' . $this->definition . '}}')];
            }

            public function handle(array $params): ResponseInterface
            {
                return new JsonApiResponse(200, []);
            }
        };
    }
}
