<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\MultiAuthorizator;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Handlers\ApiListingHandler;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;

class ApiListingHandlerTest extends TestCase
{
    public function testDefaultHandle()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider();
        $apiDecider->addApi(
            new EndpointIdentifier('POST', 2, 'comments', 'list'),
            new AlwaysOkHandler(),
            new NoAuthorization()
        );

        $apiDecider->addApi(
            new EndpointIdentifier('GET', 2, 'endpoints'),
            new ApiListingHandler($apiDecider, $apiLink),
            new NoAuthorization()
        );

        $result = $apiDecider->getApi('GET', 2, 'endpoints');
        $handler = $result->getHandler();

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
        $apiDecider->addApi(
            new EndpointIdentifier('POST', 1, 'comments', 'list'),
            new EchoHandler(),
            new NoAuthorization()
        );

        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'endpoints'),
            new ApiListingHandler($apiDecider, $apiLink),
            new NoAuthorization()
        );

        $result = $apiDecider->getApi('GET', 1, 'endpoints');
        $handler = $result->getHandler();

        $response = $handler->handle([]);
        $this->assertEquals(200, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals(2, count($payload['endpoints']));
        $this->assertEquals(2, count($payload['endpoints'][0]['params']));
    }

    public function testMultiauthorizatorHandler()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);

        $apiDecider = new ApiDecider();

        $_GET['api_key'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $queryAuthorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);

        $_SERVER['HTTP_X_API_KEY'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $headerAuthorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);

        $multiAuthorizator = new MultiAuthorizator([$queryAuthorization, $headerAuthorization]);

        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'endpoints'),
            new ApiListingHandler($apiDecider, $apiLink),
            $multiAuthorizator
        );

        $result = $apiDecider->getApi('GET', 1, 'endpoints');
        $handler = $result->getHandler();

        $response = $handler->handle([]);
        $this->assertEquals(200, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals(1, count($payload['endpoints']));
    }
}
