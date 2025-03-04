<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Handlers\DefaultHandler;

class ApiDeciderTest extends TestCase
{
    /** @var Container */
    private $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testDefaultHandlerWithNoRegisteredHandlers()
    {

        $apiDecider = new ApiDecider($this->container);
        $result = $apiDecider->getApi('POST', '1', 'article', 'list');

        $this->assertInstanceOf(EndpointIdentifier::class, $result->getEndpoint());
        $this->assertInstanceOf(NoAuthorization::class, $result->getAuthorization());
        $this->assertInstanceOf(DefaultHandler::class, $result->getHandler());
    }

    public function testFindRightHandler()
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $result = $apiDecider->getApi('POST', '2', 'comments', 'list');

        $this->assertInstanceOf(EndpointIdentifier::class, $result->getEndpoint());
        $this->assertInstanceOf(NoAuthorization::class, $result->getAuthorization());
        $this->assertInstanceOf(AlwaysOkHandler::class, $result->getHandler());

        $this->assertEquals('POST', $result->getEndpoint()->getMethod());
        $this->assertEquals('2', $result->getEndpoint()->getVersion());
        $this->assertEquals('comments', $result->getEndpoint()->getPackage());
        $this->assertEquals('list', $result->getEndpoint()->getApiAction());
    }

    public function testGetHandlers()
    {
        $apiDecider = new ApiDecider($this->container);

        $this->assertEquals(0, count($apiDecider->getApis()));

        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $this->assertEquals(1, count($apiDecider->getApis()));
    }

    public function testGlobalPreflight()
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->enableGlobalPreflight();

        $this->assertEquals(0, count($apiDecider->getApis()));

        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $this->assertEquals(1, count($apiDecider->getApis()));

        $handler = $apiDecider->getApi('OPTIONS', '2', 'comments', 'list');
        $this->assertInstanceOf(CorsPreflightHandler::class, $handler->getHandler());
    }
}
