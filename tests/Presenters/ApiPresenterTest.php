<?php

namespace Tomaj\NetteApi\Test\Presenters;

use PHPUnit\Framework\TestCase;
use Nette\Application\Request;
use Nette\DI\Container;
use Nette\Http\Response as HttpResponse;
use Nette\Http\Request as HttpRequest;
use Nette\Http\UrlScript;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\StaticBearerTokenRepository;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\Test\Handler\TestHandler;
use Tracy\Debugger;

class ApiPresenterTest extends TestCase
{
    public function testSimpleResponse()
    {
        $apiDecider = new ApiDecider();
        $apiDecider->addApi(new EndpointIdentifier('GET', 1, 'test', 'api'), new AlwaysOkHandler(), new NoAuthorization());

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->injectPrimary(new Container(), null, null, new HttpRequest(new UrlScript()), new HttpResponse());

        $request = new Request('Api:Api:default', 'GET', ['version' => 1, 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);

        $this->assertEquals(200, $result->getCode());
        $this->assertEquals(['status' => 'ok'], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());
        $this->assertEquals('utf-8', $result->getCharset());
    }

    public function testWithAuthorization()
    {
        $apiDecider = new ApiDecider();
        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'test', 'api'),
            new AlwaysOkHandler(),
            new BearerTokenAuthorization(new StaticBearerTokenRepository([]), new IpDetector())
        );

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->injectPrimary(new Container(), null, null, new HttpRequest(new UrlScript()), new HttpResponse());

        $request = new Request('Api:Api:default', 'GET', ['version' => 1, 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);

        $this->assertEquals(['status' => 'error', 'message' => 'Authorization header HTTP_Authorization is not set'], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());
    }

    public function testWithParams()
    {
        $apiDecider = new ApiDecider();
        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'test', 'api'),
            new EchoHandler(),
            new NoAuthorization()
        );

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->injectPrimary(new Container(), null, null, new HttpRequest(new UrlScript()), new HttpResponse());

        $request = new Request('Api:Api:default', 'GET', ['version' => 1, 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);
        
        $this->assertEquals(['status' => 'error', 'message' => 'wrong input'], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());

        Debugger::enable(true);
        $result = $presenter->run($request);
        $this->assertEquals(['status' => 'error', 'message' => 'wrong input', 'detail' => ['status' => ['NULL value found, but a string is required', 'Does not have a value in the enumeration ["ok","error"]']]], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());
        Debugger::enable(false);
    }

    public function testWithOutputs()
    {
        $apiDecider = new ApiDecider();
        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'test', 'api'),
            new TestHandler(),
            new NoAuthorization()
        );

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->injectPrimary(new Container(), null, null, new HttpRequest(new UrlScript()), new HttpResponse());

        $request = new Request('Api:Api:default', 'GET', ['version' => 1, 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);

        $this->assertEquals(['hello' => 'world'], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());
    }
}
