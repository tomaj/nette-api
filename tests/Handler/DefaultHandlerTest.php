<?php

namespace Tomaj\NetteApi\Test\Handler;

use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\Http\Url;
use PHPUnit_Framework_TestCase;

class DefaultHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testResponse()
    {
        $defaultHandler = new DefaultHandler();
        $result = $defaultHandler->handle([]);
        $this->assertEquals(500, $result->getCode());
        $this->assertEquals('application/json', $result->getContentType());
        $this->assertEquals('utf-8', $result->getCharset());
        $this->assertEquals(['status' => 'error', 'message' => 'Unknown api endpoint'], $result->getPayload());
    }

    public function testEndpointSetter()
    {
        $defaultHandler = new DefaultHandler();
        $this->assertNull($defaultHandler->getEndpoint());

        $endpointIdentifier = new EndpointIdentifier('POST', 1, 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);
        $this->assertEquals($endpointIdentifier, $defaultHandler->getEndpoint());
    }

    /**
     * @expectedException \Nette\InvalidStateException
     */
    public function testExceptionWhenCreatingLinkWithoutLinkGenerator()
    {
        $defaultHandler = new DefaultHandler();
        $defaultHandler->createLink([]);
    }

    /**
     * @expectedException \Nette\InvalidStateException
     */
    public function testExceptionWHenCreatingLinkWithoutEndpoint()
    {
        $defaultHandler = new DefaultHandler();

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new Url('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $defaultHandler->createLink([]);
    }

    public function testCreateLink()
    {
        $defaultHandler = new DefaultHandler();

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new Url('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $endpointIdentifier = new EndpointIdentifier('POST', 1, 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);

        $this->assertEquals('http://test/?version=1&package=article&apiAction=detail&page=2&action=default&presenter=Api%3AApi', $defaultHandler->createLink(['page' => 2]));
    }
}
