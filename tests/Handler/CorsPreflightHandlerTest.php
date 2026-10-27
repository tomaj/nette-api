<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\InvalidStateException;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;

final class CorsPreflightHandlerTest extends TestCase
{
    public function testResponse(): void
    {
        $preflighthandler = new CorsPreflightHandler(new Response());
        $result = $preflighthandler->handle([]);
        $this->assertSame(200, $result->getCode());
        $this->assertEquals([], $result->getPayload());
    }

    public function testEndpointSetter(): void
    {
        $defaultHandler = new CorsPreflightHandler(new Response());
        $this->assertNull($defaultHandler->getEndpoint());

        $endpointIdentifier = new EndpointIdentifier('OPTIONS', '1', 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);
        $this->assertEquals($endpointIdentifier, $defaultHandler->getEndpoint());
    }

    public function testExceptionWhenCreatingLinkWithoutLinkGenerator(): void
    {
        $defaultHandler = new CorsPreflightHandler(new Response());

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('You have setupLinkGenerator for this handler if you want to generate link in this handler');
        $defaultHandler->createLink([]);
    }

    public function testExceptionWHenCreatingLinkWithoutEndpoint(): void
    {
        $defaultHandler = new CorsPreflightHandler(new Response());

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('You have setEndpoint() for this handler if you want to generate link in this handler');
        $defaultHandler->createLink([]);
    }

    public function testCreateLink(): void
    {
        $defaultHandler = new CorsPreflightHandler(new Response());

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $endpointIdentifier = new EndpointIdentifier('OPTIONS', '1', 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);

        $this->assertSame('http://test/?version=1&package=article&apiAction=detail&page=2&action=default&presenter=Api%3AApi', $defaultHandler->createLink(['page' => 2]));
    }

    #[RequiresFunction('xdebug_get_headers')]
    public function testDefaultHeaders(): void
    {
        new CorsPreflightHandler(new Response())->handle([]);
        $this->assertSame([
            'Access-Control-Allow-Headers: Authorization',
            'Access-Control-Allow-Headers: X-Requested-With',
        ], xdebug_get_headers());
    }

    public function testDefaultHeadersConfiguration(): void
    {
        $handler = new CorsPreflightHandler(new Response());
        $this->assertSame(
            ['Access-Control-Allow-Headers' => ['Authorization', 'X-Requested-With']],
            new \ReflectionProperty($handler, 'headers')->getValue($handler)
        );
    }
}
