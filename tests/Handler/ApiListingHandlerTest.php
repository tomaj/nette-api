<?php

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Handlers\ApiListingHandler;
use Tomaj\NetteApi\Link\ApiLink;

class ApiListingHandlerTest extends TestCase
{
    public function testDefaultHandle()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider();
        $apiDecider->addApiHandler(
            new EndpointIdentifier('POST', 2, 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $apiDecider->addApiHandler(
            new EndpointIdentifier('GET', 2, 'endpoints'),
            new ApiListingHandler($apiDecider, $apiLink),
            new NoAuthorization()
        );

        $result = $apiDecider->getApiHandler('GET', 2, 'endpoints');
        $handler = $result['handler'];

        $response = $handler->handle([]);
        $this->assertEquals(200, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals(2, count($payload['endpoints']));
    }

    public function testHandlerWithParam()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider();
        $apiDecider->addApiHandler(
            new EndpointIdentifier('POST', 1, 'comments', 'list'),
            new EchoHandler(),
            new NoAuthorization()
        );

        $apiDecider->addApiHandler(
            new EndpointIdentifier('GET', 1, 'endpoints'),
            new ApiListingHandler($apiDecider, $apiLink),
            new NoAuthorization()
        );

        $result = $apiDecider->getApiHandler('GET', 1, 'endpoints');
        $handler = $result['handler'];

        $response = $handler->handle([]);
        $this->assertEquals(200, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals(2, count($payload['endpoints']));
        $this->assertEquals(2, count($payload['endpoints'][0]['params']));
    }
}
