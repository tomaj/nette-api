<?php

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Http\Response;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\Http\Url;
use PHPUnit_Framework_TestCase;

class CorsPreflightHandlerTest extends PHPUnit_Framework_TestCase
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

        $endpointIdentifier = new EndpointIdentifier('OPTION', 1, 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);
        $this->assertEquals($endpointIdentifier, $defaultHandler->getEndpoint());
    }

    /**
     * @expectedException \Nette\InvalidStateException
     */
    public function testExceptionWhenCreatingLinkWithoutLinkGenerator()
    {
        $defaultHandler = new CorsPreflightHandler(new Response());
        $defaultHandler->createLink([]);
    }

    /**
     * @expectedException \Nette\InvalidStateException
     */
    public function testExceptionWHenCreatingLinkWithoutEndpoint()
    {
        $defaultHandler = new CorsPreflightHandler(new Response());

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new Url('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $defaultHandler->createLink([]);
    }

    public function testCreateLink()
    {
        $defaultHandler = new CorsPreflightHandler(new Response());

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new Url('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $endpointIdentifier = new EndpointIdentifier('OPTION', 1, 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);

        $this->assertEquals('http://test/?version=1&package=article&apiAction=detail&page=2&action=default&presenter=Api%3AApi', $defaultHandler->createLink(['page' => 2]));
    }
}
