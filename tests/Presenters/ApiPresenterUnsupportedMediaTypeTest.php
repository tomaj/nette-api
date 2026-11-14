<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use Nette\Application\Request;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Http\IRequest;
use Nette\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Params\PathValues;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;

final class ApiPresenterUnsupportedMediaTypeTest extends TestCase
{
    /** @var array<array-key, mixed> */
    private array $server;

    /** @var list<int> */
    public array $logged = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'text/csv';
        $_POST = [];
        $_FILES = [];
        RequestInput::reset();
        PathValues::reset();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        RequestInput::reset();
    }

    public function testUnsupportedMediaTypeIsAnsweredWith415AndLogged(): void
    {
        $presenter = $this->presenter(new NoAuthorization());
        $response = $presenter->run($this->request());
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertSame(415, $response->getCode());
        $this->assertSame(415, $presenter->response->getCode());
        $this->assertSame([415], $this->logged);
        $this->assertSame("Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded.", $response->getPayload()['detail']);
    }

    public function testAuthorizationIsCheckedBeforeTheMediaType(): void
    {
        $presenter = $this->presenter(new BearerTokenAuthorization(new StaticTokenRepository(['secret' => '*']), new StaticIpDetector('127.0.0.1')));
        $response = $presenter->run($this->request());
        $this->assertSame(401, $response->getCode());
        $this->assertSame([], $this->logged);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
        RequestInput::reset();
        $this->assertSame(415, $presenter->run($this->request())->getCode());
        $this->assertSame([415], $this->logged);
    }

    public function testRequestPathFailureOtherThanAMissingServiceIsNotHidden(): void
    {
        $presenter = $this->presenter(new NoAuthorization(), new \RuntimeException('broken http request'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('broken http request');
        // the request path is read only for an endpoint the static lookup did not find
        $presenter->run(new Request('Api:Api:default', 'POST', ['version' => '1', 'package' => 'unknown']));
    }

    public function testWithoutAnHttpRequestServiceAnUnknownEndpointIsNotFound(): void
    {
        $presenter = $this->presenter(new NoAuthorization());
        $response = $presenter->run(new Request('Api:Api:default', 'POST', ['version' => '1', 'package' => 'unknown']));
        $this->assertSame(404, $response->getCode());
    }

    public function testAStaticEndpointDoesNotReadTheRequestPath(): void
    {
        $presenter = $this->presenter(new NoAuthorization(), new \RuntimeException('broken http request'));
        $this->assertSame(415, $presenter->run($this->request())->getCode());
    }

    public function testARequestStartsWithoutPathValuesEvenWhenGetApiIsOverridden(): void
    {
        $presenter = $this->presenter(new NoAuthorization());
        $presenter->apiDecider = new class (new Container()) extends ApiDecider {
            public function getApi(string $method, string $version, string $package, ?string $apiAction = null): Api
            {
                return new Api(new EndpointIdentifier('POST', 1, 'orders'), new CreateOrderHandler(), new NoAuthorization());
            }
        };
        PathValues::set(['id' => 'left over from the previous request']);

        $presenter->run($this->request());

        $this->assertFalse(PathValues::has('id'));
    }

    private function request(): Request
    {
        return new Request('Api:Api:default', 'POST', ['version' => '1', 'package' => 'orders']);
    }

    private function presenter(ApiAuthorizationInterface $authorization, ?\Throwable $httpRequestFailure = null): ApiPresenter
    {
        $logger = new readonly class ($this) implements ApiLoggerInterface {
            public function __construct(private ApiPresenterUnsupportedMediaTypeTest $test)
            {
            }

            public function log(int $responseCode, string $requestMethod, string $requestHeader, string $requestUri, string $requestIp, string $requestAgent, int $responseTime): bool
            {
                $this->test->logged[] = $responseCode;
                return true;
            }
        };

        $container = new class ($logger, $httpRequestFailure) extends Container {
            public function __construct(private readonly ApiLoggerInterface $logger, private readonly ?\Throwable $httpRequestFailure)
            {
                parent::__construct();
            }

            public function findByType(string $type): array
            {
                return $type === ApiLoggerInterface::class ? ['logger'] : [];
            }

            public function getByType(string $type, bool $throw = true): ?object
            {
                if ($type === IRequest::class) {
                    if ($this->httpRequestFailure !== null) {
                        throw $this->httpRequestFailure;
                    }

                    return $throw ? throw new MissingServiceException('no http request') : null;
                }

                return $type === ApiLoggerInterface::class ? $this->logger : new StaticIpDetector('127.0.0.1');
            }
        };

        $apiDecider = new ApiDecider(new Container());
        $apiDecider->addApi(new EndpointIdentifier('POST', 1, 'orders'), new CreateOrderHandler(), $authorization);

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = $container;
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);

        return $presenter;
    }
}
