<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Handlers\DefaultHandler;

class ApiDeciderTest extends TestCase
{
    public function testDefaultHandlerWithNoRegisteredHandlers()
    {
        $apiDecider = new ApiDecider();
        $result = $apiDecider->getApiHandler('POST', 1, 'article', 'list');

        $this->assertInstanceOf(EndpointIdentifier::class, $result['endpoint']);
        $this->assertInstanceOf(NoAuthorization::class, $result['authorization']);
        $this->assertInstanceOf(DefaultHandler::class, $result['handler']);
    }

    public function testFindRightHandler()
    {
        $apiDecider = new ApiDecider();
        $apiDecider->addApiHandler(
            new EndpointIdentifier('POST', 2, 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $result = $apiDecider->getApiHandler('POST', 2, 'comments', 'list');

        $this->assertInstanceOf(EndpointIdentifier::class, $result['endpoint']);
        $this->assertInstanceOf(NoAuthorization::class, $result['authorization']);
        $this->assertInstanceOf(AlwaysOkHandler::class, $result['handler']);

        $this->assertEquals('POST', $result['endpoint']->getMethod());
        $this->assertEquals(2, $result['endpoint']->getVersion());
        $this->assertEquals('comments', $result['endpoint']->getPackage());
        $this->assertEquals('list', $result['endpoint']->getApiAction());
    }

    public function testGetHandlers()
    {
        $apiDecider = new ApiDecider();

        $this->assertEquals(0, count($apiDecider->getHandlers()));

        $apiDecider->addApiHandler(
            new EndpointIdentifier('POST', 2, 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $this->assertEquals(1, count($apiDecider->getHandlers()));
    }

    public function testGlobalPreflight()
    {
        $apiDecider = new ApiDecider();
        $apiDecider->enableGlobalPreflight();

        $this->assertEquals(0, count($apiDecider->getHandlers()));

        $apiDecider->addApiHandler(
            new EndpointIdentifier('POST', 2, 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $this->assertEquals(1, count($apiDecider->getHandlers()));

        $handler = $apiDecider->getApiHandler('OPTIONS', 2, 'comments', 'list');
        $this->assertInstanceOf(CorsPreflightHandler::class, $handler['handler']);
    }
}
