<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit_Framework_TestCase;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\Http\Url;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Link\ApiLink;

class ApiDeciderTest extends PHPUnit_Framework_TestCase
{
    public function testDefaultHandlerWithNoRegisteredHandlers()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new Url('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider($apiLink);
        $result = $apiDecider->getApiHandler('POST', 1, 'article', 'list');

        $this->assertInstanceOf('Tomaj\NetteApi\EndpointIdentifier', $result['endpoint']);
        $this->assertInstanceOf('Tomaj\NetteApi\Authorization\NoAuthorization', $result['authorization']);
        $this->assertInstanceOf('Tomaj\NetteApi\Handlers\DefaultHandler', $result['handler']);
    }

    public function testFindRightHandler()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new Url('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider($apiLink);
        $apiDecider->addApiHandler(
            new EndpointIdentifier('POST', 2, 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $result = $apiDecider->getApiHandler('POST', 2, 'comments', 'list');

        $this->assertInstanceOf('Tomaj\NetteApi\EndpointIdentifier', $result['endpoint']);
        $this->assertInstanceOf('Tomaj\NetteApi\Authorization\NoAuthorization', $result['authorization']);
        $this->assertInstanceOf('Tomaj\NetteApi\Handlers\AlwaysOkHandler', $result['handler']);

        $this->assertEquals('POST', $result['endpoint']->getMethod());
        $this->assertEquals(2, $result['endpoint']->getVersion());
        $this->assertEquals('comments', $result['endpoint']->getPackage());
        $this->assertEquals('list', $result['endpoint']->getApiAction());
    }

    public function testGetHandlers()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new Url('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider($apiLink);

        $this->assertEquals(0, count($apiDecider->getHandlers()));

        $apiDecider->addApiHandler(
            new EndpointIdentifier('POST', 2, 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $this->assertEquals(1, count($apiDecider->getHandlers()));
    }
}
