<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use Tracy\Logger;
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
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\Error\ProblemDetailsErrorHandler;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;
use Tomaj\NetteApi\Test\Error\StaticConfigurator;
use Tracy\Debugger;

final class ApiPresenterTest extends TestCase
{
    /** @var Container */
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testSimpleResponse(): void
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

    public function testWithAuthorization(): void
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

    public function testWithParams(): void
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

    public function testWithOutputs(): void
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

    private function presenter(ApiDecider $apiDecider, ?ErrorHandlerInterface $errorHandler = null): ApiPresenter
    {
        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = new Container();
        $presenter->outputConfigurator = new StaticConfigurator(false);
        $presenter->errorHandler = $errorHandler ?? new DefaultErrorHandler($presenter->outputConfigurator);
        return $presenter;
    }

    private function request(string $action = 'api'): Request
    {
        return new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => $action]);
    }

    private function rateLimit(RateLimitResponse $response): RateLimitInterface
    {
        return new readonly class ($response) implements RateLimitInterface {
            public function __construct(private RateLimitResponse $response)
            {
            }

            public function check(): ?RateLimitResponse
            {
                return $this->response;
            }
        };
    }

    public function testUnknownEndpointGoesThroughErrorHandler(): void
    {
        $presenter = $this->presenter(new ApiDecider($this->container));
        $result = $presenter->run($this->request('missing'));
        $this->assertInstanceOf(JsonApiResponse::class, $result);
        $this->assertSame(404, $result->getCode());
        $this->assertSame(404, $presenter->response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'Unknown api endpoint'], $result->getPayload());

        $presenter = $this->presenter(new ApiDecider($this->container), new ProblemDetailsErrorHandler(new StaticConfigurator(false)));
        $result = $presenter->run($this->request('missing'));
        $this->assertInstanceOf(ProblemDetailsResponse::class, $result);
        $this->assertSame(['type' => 'about:blank', 'status' => 404, 'title' => 'Not Found', 'detail' => 'Unknown api endpoint'], $result->getProblem()->toArray());
        $this->assertSame(404, $presenter->response->getCode());
    }

    public function testRateLimitGoesThroughErrorHandler(): void
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), new NoAuthorization(), $this->rateLimit(new RateLimitResponse(10, 0, 60)));

        $presenter = $this->presenter($apiDecider, new ProblemDetailsErrorHandler(new StaticConfigurator(false)));
        $result = $presenter->run($this->request());
        $this->assertInstanceOf(ProblemDetailsResponse::class, $result);
        $this->assertSame(429, $result->getCode());
        $this->assertSame(429, $presenter->response->getCode());
        $this->assertSame(['limit' => 10, 'retryAfter' => 60], $result->getProblem()->extensions);

        $result = $this->presenter($apiDecider)->run($this->request());
        $this->assertInstanceOf(JsonApiResponse::class, $result);
        $this->assertSame(['status' => 'error', 'message' => 'Too many requests. Retry after 60 seconds.'], $result->getPayload());
    }

    public function testRateLimitOwnErrorResponseWins(): void
    {
        $own = new JsonApiResponse(429, ['custom' => true]);
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), new NoAuthorization(), $this->rateLimit(new RateLimitResponse(10, 0, 60, $own)));
        $this->assertSame($own, $this->presenter($apiDecider, new ProblemDetailsErrorHandler(new StaticConfigurator(false)))->run($this->request()));
    }

    public function testRateLimitWithRemainingRequestsContinues(): void
    {
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), new NoAuthorization(), $this->rateLimit(new RateLimitResponse(10, 1, 60)));
        $this->assertSame(200, $this->presenter($apiDecider)->run($this->request())->getCode());
    }

    public function testAuthorizationExceptionDoesNotLeakMessage(): void
    {
        $authorization = new class implements ApiAuthorizationInterface {
            public function authorized(): bool
            {
                throw new \RuntimeException('SQLSTATE secret');
            }

            public function getErrorMessage(): ?string
            {
                return null;
            }
        };
        $apiDecider = new ApiDecider($this->container);
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), $authorization);

        $logDirectory = sys_get_temp_dir() . '/nette-api-presenter-' . bin2hex(random_bytes(4));
        mkdir($logDirectory);
        Debugger::setLogger(new Logger($logDirectory));
        $presenter = $this->presenter($apiDecider);
        $result = $presenter->run($this->request());
        array_map(unlink(...), glob($logDirectory . '/*') ?: []);
        rmdir($logDirectory);

        $this->assertSame(500, $result->getCode());
        $this->assertSame(500, $presenter->response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error'], $result->getPayload());
    }
}
