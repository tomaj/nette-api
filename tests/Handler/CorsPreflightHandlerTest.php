<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\InvalidStateException;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;

class CorsPreflightHandlerTest extends TestCase
{
    public function testResponse()
    {
        $preflighthandler = new CorsPreflightHandler(new Response());
        $result = $preflighthandler->handle([]);
        $this->assertEquals(200, $result->getCode());
        $this->assertEquals([], $result->getPayload());
    }

    public function testEndpointSetter()
    {
        $defaultHandler = new CorsPreflightHandler(new Response());
        $this->assertNull($defaultHandler->getEndpoint());

        $endpointIdentifier = new EndpointIdentifier('OPTIONS', '1', 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);
        $this->assertEquals($endpointIdentifier, $defaultHandler->getEndpoint());
    }

    public function testExceptionWhenCreatingLinkWithoutLinkGenerator()
    {
        $defaultHandler = new CorsPreflightHandler(new Response());

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('You have setupLinkGenerator for this handler if you want to generate link in this handler');
        $defaultHandler->createLink([]);
    }

    public function testExceptionWHenCreatingLinkWithoutEndpoint()
    {
        $defaultHandler = new CorsPreflightHandler(new Response());

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('You have setEndpoint() for this handler if you want to generate link in this handler');
        $defaultHandler->createLink([]);
    }

    public function testCreateLink()
    {
        $defaultHandler = new CorsPreflightHandler(new Response());

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $endpointIdentifier = new EndpointIdentifier('OPTIONS', '1', 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);

        $this->assertEquals('http://test/?version=1&package=article&apiAction=detail&page=2&action=default&presenter=Api%3AApi', $defaultHandler->createLink(['page' => 2]));
    }
}
