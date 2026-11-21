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
use Tomaj\NetteApi\Params\PathInputParam;

final class ApiDeciderTest extends TestCase
{
    /** @var Container */
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testDefaultHandlerWithNoRegisteredHandlers(): void
    {

        $apiDecider = new ApiDecider($this->container);
        $result = $apiDecider->getApi('POST', '1', 'article', 'list');

        $this->assertInstanceOf(EndpointIdentifier::class, $result->getEndpoint());
        $this->assertInstanceOf(NoAuthorization::class, $result->getAuthorization());
        $this->assertInstanceOf(DefaultHandler::class, $result->getHandler());
    }

    public function testThePathParamsOfAHandlerAreCheckedOnce(): void
    {
        $handler = new class extends AlwaysOkHandler {
            public int $calls = 0;

            public function params(): array
            {
                $this->calls++;
                return parent::params();
            }
        };
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', 1, 'users', 'list'), $handler, new NoAuthorization());

        $this->assertSame($handler, $apiDecider->getApi('GET', '1', 'users', 'list')->getHandler());
        $this->assertSame($handler, $apiDecider->getApi('GET', '1', 'users', 'list')->getHandler());
        $this->assertSame(1, $handler->calls, 'the configuration is checked once, not on every request');
    }

    public function testParamsThatCannotBeReadYetAreCheckedOnTheNextLookup(): void
    {
        $handler = new class extends AlwaysOkHandler {
            public int $calls = 0;

            public function params(): array
            {
                if (++$this->calls === 1) {
                    throw new \RuntimeException('not ready');
                }
                return [new PathInputParam('id')];
            }
        };
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', 1, 'users', 'list'), $handler, new NoAuthorization());

        $this->assertSame($handler, $apiDecider->getApi('GET', '1', 'users', 'list')->getHandler(), 'the presenter answers the exception after the authorization');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("the endpoint URL 'v1/users/list' and its path parameters do not match");
        $apiDecider->getApi('GET', '1', 'users', 'list');
    }

    public function testAPathParamOfAPlainHandlerNeedsItsSegmentInTheUrl(): void
    {
        $handler = new class extends AlwaysOkHandler {
            public function params(): array
            {
                return [new PathInputParam('id')];
            }
        };
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', 1, 'users', 'detail'), $handler, new NoAuthorization());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The URL has {} and the handler declares [id]');
        $apiDecider->getApis();
    }

    public function testAMalformedTemplateIsRefusedWhenItIsAdded(): void
    {
        $apiDecider = new ApiDecider($this->container);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("GET v1/users/{id: '{id' is not a path parameter in the endpoint URL 'v1/users/{id'.");
        $apiDecider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id'), AlwaysOkHandler::class, new NoAuthorization());
    }

    public function testAnEndpointWithATemplateIsFoundByItsLiteralUrl(): void
    {
        $handler = new class extends AlwaysOkHandler {
            public function params(): array
            {
                return [new PathInputParam('id')];
            }
        };
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), $handler, new NoAuthorization());

        // the API console opens the endpoint like this
        $this->assertSame($handler, $apiDecider->getApi('GET', '1', 'users', '{id}/detail')->getHandler());
    }

    public function testFindRightHandler(): void
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

        $this->assertSame('POST', $result->getEndpoint()->getMethod());
        $this->assertEquals('2', $result->getEndpoint()->getVersion());
        $this->assertSame('comments', $result->getEndpoint()->getPackage());
        $this->assertSame('list', $result->getEndpoint()->getApiAction());
    }

    public function testGetHandlers(): void
    {
        $apiDecider = new ApiDecider($this->container);

        $this->assertCount(0, $apiDecider->getApis());

        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $this->assertCount(1, $apiDecider->getApis());
    }

    public function testGlobalPreflight(): void
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->enableGlobalPreflight();

        $this->assertCount(0, $apiDecider->getApis());

        $apiDecider->addApi(
            new EndpointIdentifier('POST', '2', 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $this->assertCount(1, $apiDecider->getApis());

        $handler = $apiDecider->getApi('OPTIONS', '2', 'comments', 'list');
        $this->assertInstanceOf(CorsPreflightHandler::class, $handler->getHandler());
    }
}
