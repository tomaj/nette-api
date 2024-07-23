<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\DI\Container;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\OpenApiHandler;
use Tomaj\NetteApi\Link\ApiLink;
use Nette\Http\Request;

class OpenApiHandlerTest extends TestCase
{
    /** @var Container */
    private $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testHandlerWithMultipleResponseSchemas()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);
        $request = new Request(new UrlScript('http://test/'));

        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'test'),
            new MultipleOutputTestHandler(),
            new NoAuthorization()
        );

        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'docs', 'open-api'),
            new OpenApiHandler($apiDecider, $apiLink, $request),
            new NoAuthorization()
        );

        $result = $apiDecider->getApi('GET', '1', 'docs', 'open-api');
        $handler = $result->getHandler();

        $response = $handler->handle(['format' => 'json']);
        $this->assertEquals(200, $response->getCode());
        $payload = $response->getPayload();

        $this->assertEquals(2, count($payload['paths']));

        $def = array_values($payload['paths'])[0]; // MultipleOutputTestHandler
        $this->assertEquals(2, count($def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema']['oneOf']));

        $def = array_values($payload['paths'])[1]; // OpenApiHandler
        $this->assertFalse(isset($def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema']['oneOf']));
    }
}
