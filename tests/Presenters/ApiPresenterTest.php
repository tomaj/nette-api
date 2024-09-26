<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use Nette\Application\Request;
use Nette\DI\Container;
use Nette\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\Test\Handler\TestHandler;
use Tracy\Debugger;

class ApiPresenterTest extends TestCase
{
    /** @var Container */
    private $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testSimpleResponse()
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), new NoAuthorization());

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = new Container();
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);

        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);

        $this->assertEquals(200, $result->getCode());
        $this->assertEquals(['status' => 'ok'], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());
        $this->assertEquals('utf-8', $result->getCharset());
    }

    public function testWithAuthorization()
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'test', 'api'),
            new AlwaysOkHandler(),
            new BearerTokenAuthorization(new StaticTokenRepository([]), new IpDetector())
        );

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = new Container();
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);

        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);

        $this->assertEquals(['status' => 'error', 'message' => 'Authorization header HTTP_Authorization is not set'], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());
    }

    public function testWithParams()
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'test', 'api'),
            new EchoHandler(),
            new NoAuthorization()
        );

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = new Container();
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);

        Debugger::$productionMode = Debugger::PRODUCTION;

        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);

        $this->assertEquals(['status' => 'error', 'message' => 'wrong input'], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());

        Debugger::$productionMode = Debugger::DETECT;

        $result = $presenter->run($request);
        $this->assertEquals(['status' => 'error', 'message' => 'wrong input', 'detail' => ['status' => ['Field is required']]], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());
    }

    public function testWithOutputs()
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '1', 'test', 'api'),
            new TestHandler(),
            new NoAuthorization()
        );

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = new Container();
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);

        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']);
        $result = $presenter->run($request);

        $this->assertEquals(['hello' => 'world'], $result->getPayload());
        $this->assertEquals('application/json', $result->getContentType());
    }
}
