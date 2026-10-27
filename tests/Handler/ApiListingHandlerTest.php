<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\DI\Container;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Handlers\ApiListingHandler;
use Tomaj\NetteApi\Link\ApiLink;

final class ApiListingHandlerTest extends TestCase
{
    /** @var Container */
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testDefaultHandle(): void
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $apiDecider->addApi(
            new EndpointIdentifier('GET', '2', 'endpoints'),
            new ApiListingHandler($apiDecider, $apiLink),
            new NoAuthorization()
        );

        $result = $apiDecider->getApi('GET', '2', 'endpoints');
        $handler = $result->getHandler();

        $response = $handler->handle([]);
        $this->assertSame(200, $response->getCode());
        $payload = $response->getPayload();
        $this->assertCount(2, $payload['endpoints']);
    }

    public function testHandlerWithParam(): void
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('POST', '1', 'comments', 'list'),
            new EchoHandler(),
            new NoAuthorization()
        );

        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'endpoints'),
            new ApiListingHandler($apiDecider, $apiLink),
            new NoAuthorization()
        );

        $result = $apiDecider->getApi('GET', '1', 'endpoints');
        $handler = $result->getHandler();

        $response = $handler->handle([]);
        $this->assertSame(200, $response->getCode());
        $payload = $response->getPayload();
        $this->assertCount(2, $payload['endpoints']);
        $this->assertCount(2, $payload['endpoints'][0]['params']);
    }

    public function testListsOnlyEndpointsOfTheRequestedVersion(): void
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '3', 'comments', 'detail'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '2', 'endpoints'),
            new ApiListingHandler($apiDecider, $apiLink),
            new NoAuthorization()
        );

        $response = $apiDecider->getApi('GET', '2', 'endpoints')->getHandler()->handle([]);
        $payload = $response->getPayload();
        $this->assertIsArray($payload);
        $this->assertCount(2, $payload['endpoints']);
        $this->assertSame([
            'method' => 'POST',
            'version' => '2',
            'package' => 'comments',
            'api_action' => 'list',
            'authorization' => NoAuthorization::class,
            'url' => $apiLink->link(new EndpointIdentifier('POST', '2', 'comments', 'list')),
            'params' => [],
        ], array_values($payload['endpoints'])[0]);
    }
}
