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

        self::assertInstanceOf(EndpointIdentifier::class, $result->getEndpoint());
        self::assertInstanceOf(NoAuthorization::class, $result->getAuthorization());
        self::assertInstanceOf(DefaultHandler::class, $result->getHandler());
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

        self::assertInstanceOf(EndpointIdentifier::class, $result->getEndpoint());
        self::assertInstanceOf(NoAuthorization::class, $result->getAuthorization());
        self::assertInstanceOf(AlwaysOkHandler::class, $result->getHandler());

        self::assertEquals('POST', $result->getEndpoint()->getMethod());
        self::assertEquals('2', $result->getEndpoint()->getVersion());
        self::assertEquals('comments', $result->getEndpoint()->getPackage());
        self::assertEquals('list', $result->getEndpoint()->getApiAction());
    }

    public function testGetHandlers()
    {
        $apiDecider = new ApiDecider($this->container);

        self::assertEquals(0, count($apiDecider->getApis()));

        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        self::assertEquals(1, count($apiDecider->getApis()));
    }

    public function testGlobalPreflight()
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->enableGlobalPreflight();

        self::assertEquals(0, count($apiDecider->getApis()));

        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        self::assertEquals(1, count($apiDecider->getApis()));

        $handler = $apiDecider->getApi('OPTIONS', '2', 'comments', 'list');
        self::assertInstanceOf(CorsPreflightHandler::class, $handler->getHandler());
    }
}
