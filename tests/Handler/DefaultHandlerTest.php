<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Http\UrlScript;
use Nette\InvalidStateException;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;

class DefaultHandlerTest extends TestCase
{
    public function testResponse()
    {
        $defaultHandler = new DefaultHandler();
        $result = $defaultHandler->handle([]);
        $this->assertEquals(404, $result->getCode());
        $this->assertEquals('application/json', $result->getContentType());
        $this->assertEquals('utf-8', $result->getCharset());
        $this->assertEquals(['status' => 'error', 'message' => 'Unknown api endpoint'], $result->getPayload());
    }

    public function testEndpointSetter()
    {
        $defaultHandler = new DefaultHandler();
        $this->assertNull($defaultHandler->getEndpoint());

        $endpointIdentifier = new EndpointIdentifier('POST', '1', 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);
        $this->assertEquals($endpointIdentifier, $defaultHandler->getEndpoint());
    }

    public function testExceptionWhenCreatingLinkWithoutLinkGenerator()
    {
        $defaultHandler = new DefaultHandler();

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('You have setupLinkGenerator for this handler if you want to generate link in this handler');
        $defaultHandler->createLink([]);
    }

    public function testExceptionWHenCreatingLinkWithoutEndpoint()
    {
        $defaultHandler = new DefaultHandler();

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('You have setEndpoint() for this handler if you want to generate link in this handler');
        $defaultHandler->createLink([]);
    }

    public function testCreateLink()
    {
        $defaultHandler = new DefaultHandler();

        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $defaultHandler->setupLinkGenerator($linkGenerator);

        $endpointIdentifier = new EndpointIdentifier('POST', '1', 'article', 'detail');
        $defaultHandler->setEndpointIdentifier($endpointIdentifier);

        $this->assertEquals('http://test/?version=1&package=article&apiAction=detail&page=2&action=default&presenter=Api%3AApi', $defaultHandler->createLink(['page' => 2]));
    }
}
