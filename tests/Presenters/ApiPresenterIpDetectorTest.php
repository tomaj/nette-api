<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use Tracy\ILogger;
use Tracy\Debugger;
use Tomaj\NetteApi\RateLimit\RateLimitStage;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
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
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Test\Error\StaticConfigurator;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\RateLimit\FixedWindowLimiter;
use Tomaj\NetteApi\RateLimit\Key\CompositeKey;
use Tomaj\NetteApi\RateLimit\Key\EndpointKey;
use Tomaj\NetteApi\RateLimit\Key\IdentityKey;
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Nette\Http\Request as HttpRequest;
use Nette\Http\UrlScript;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\AuthorizationIdentityInterface;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;

/**
 * The IP limits without an IpDetectorInterface service; only response codes, so it runs without xdebug too
 */
final class ApiPresenterIpDetectorTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']);
    }

    private function presenter(RateLimiterInterface $rateLimit, bool $ipDetector, bool $token = false): ApiPresenter
    {
        $apiDecider = new ApiDecider(new Container());
        $authorization = $token ? new BearerTokenAuthorization(new StaticTokenRepository(['secret' => '*']), new IpDetector()) : new NoAuthorization();
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), $authorization, $rateLimit);

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = new class ($ipDetector) extends Container {
            public function __construct(private readonly bool $ipDetector)
            {
                parent::__construct();
            }

            #[\Override]
            public function getByType(string $type, bool $throw = true): ?object
            {
                return $type === IpDetectorInterface::class && $this->ipDetector ? new StaticIpDetector('1.2.3.4') : null;
            }
        };
        $presenter->outputConfigurator = new StaticConfigurator(false);
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);
        return $presenter;
    }

    private function presenterWithDefaultDetector(RateLimiterInterface $rateLimit, ?string $remoteAddress, bool $httpRequest = true): ApiPresenter
    {
        $presenter = $this->presenter($rateLimit, ipDetector: false);
        $presenter->context = new class ($remoteAddress, $httpRequest) extends Container {
            public function __construct(private readonly ?string $remoteAddress, private readonly bool $httpRequest)
            {
                parent::__construct();
            }

            #[\Override]
            public function getByType(string $type, bool $throw = true): ?object
            {
                return match ($type) {
                    IpDetectorInterface::class => new IpDetector($this->httpRequest ? new HttpRequest(new UrlScript('https://api.example.com/'), remoteAddress: $this->remoteAddress) : null),
                    default => null,
                };
            }
        };
        return $presenter;
    }

    private function failingLimiterExpected(ApiPresenter $presenter, string $message): void
    {
        $handler = $this->createMock(ErrorHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(fn (\Throwable $e): bool => $e instanceof \LogicException && str_contains($e->getMessage(), $message)), [])
            ->willReturn(new JsonApiResponse(500, ['status' => 'error']));
        $presenter->errorHandler = $handler;
        $this->assertSame(500, $this->code($presenter), 'like an exception of a handler, the error handler answers');
        $this->assertSame(500, $presenter->response->getCode());
    }

    private function code(ApiPresenter $presenter): int
    {
        $response = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']));
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        return $response->getCode();
    }

    private function limiter(RateLimitKeyInterface $key): RateLimiterInterface
    {
        return new FixedWindowLimiter('p', 1, 60, $key, new InMemoryStorage());
    }

    public function testIpLimitWithIpDetector(): void
    {
        $presenter = $this->presenter($this->limiter(new IpKey()), ipDetector: true);
        $this->assertSame([200, 429], [$this->code($presenter), $this->code($presenter)]);
    }

    public function testIpLimitWithoutIpDetectorFails(): void
    {
        $this->failingLimiterExpected(
            $this->presenter($this->limiter(new IpKey()), ipDetector: false),
            'The rate limit of endpoint GET v1/test/api is keyed by ip, but the container has no Tomaj\NetteApi\Misc\IpDetectorInterface service, so the client IP is not known. '
            . 'Register one, for example services: - Tomaj\NetteApi\Misc\IpDetector (or your own detector reading the address set by your proxy), or use another key.',
        );
    }

    public function testIpLimitAfterTheAuthorizationWithoutIpDetectorFails(): void
    {
        $presenter = $this->presenter($this->limiter(new CompositeKey(new IpKey(), new IdentityKey())), ipDetector: false, token: true);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
        $this->failingLimiterExpected($presenter, 'is keyed by ip');
    }

    public function testForgedHeadersDoNotGiveANewQuotaWithTheDefaultDetector(): void
    {
        $presenter = $this->presenterWithDefaultDetector($this->limiter(new IpKey()), '9.9.9.9');
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';
        $_SERVER['HTTP_CLIENT_IP'] = '2.2.2.2';
        $this->assertSame(200, $this->code($presenter));
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '3.3.3.3';
        $_SERVER['HTTP_CLIENT_IP'] = '4.4.4.4';
        $this->assertSame(429, $this->code($presenter));
    }

    public function testTheDefaultDetectorCountsTheAddressFromNette(): void
    {
        $limiter = $this->limiter(new IpKey());
        $_SERVER['REMOTE_ADDR'] = '7.7.7.7'; // a reverse proxy listed in http.proxy
        $this->assertSame(200, $this->code($this->presenterWithDefaultDetector($limiter, '9.9.9.9')));
        $this->assertSame(200, $this->code($this->presenterWithDefaultDetector($limiter, '8.8.8.8')), 'another client has its own quota');
        $this->assertSame(429, $this->code($this->presenterWithDefaultDetector($limiter, '9.9.9.9')));
    }

    public function testTheDefaultDetectorWithoutTheAddressFromNette(): void
    {
        $limiter = $this->limiter(new IpKey());
        $_SERVER['REMOTE_ADDR'] = '7.7.7.7';
        $this->assertSame(200, $this->code($this->presenterWithDefaultDetector($limiter, null, httpRequest: false)));
        $this->assertSame(429, $this->code($this->presenterWithDefaultDetector($limiter, null)), 'REMOTE_ADDR without an address from Nette');
        unset($_SERVER['REMOTE_ADDR']);
        $this->assertSame(200, $this->code($this->presenterWithDefaultDetector($limiter, null)), 'a command line request has its own quota');
        $this->assertSame(429, $this->code($this->presenterWithDefaultDetector($limiter, null, httpRequest: false)));
    }

    public function testAnEmptyAddressIsNotAKeyThatSkipsTheLimit(): void
    {
        $limiter = $this->limiter(new IpKey());
        $_SERVER['REMOTE_ADDR'] = '';
        $presenter = $this->presenterWithDefaultDetector($limiter, null);
        $this->assertSame([200, 429], [$this->code($presenter), $this->code($presenter)]);
    }

    public function testAnExceptionOfAnIpDetectorGoesToTheErrorHandler(): void
    {
        $presenter = $this->presenter($this->limiter(new IpKey()), ipDetector: false);
        $presenter->context = new class extends Container {
            #[\Override]
            public function getByType(string $type, bool $throw = true): ?object
            {
                return new class implements IpDetectorInterface {
                    public function getRequestIp(): string
                    {
                        throw new \LogicException('a bug in the detector');
                    }
                };
            }
        };
        $this->failingLimiterExpected($presenter, 'a bug in the detector');
    }

    public function testAFailingIpDetectorDoesNotFailALimiterWithoutTheIp(): void
    {
        $presenter = $this->presenter($this->limiter(new EndpointKey()), ipDetector: false);
        $presenter->context = new class extends Container {
            #[\Override]
            public function getByType(string $type, bool $throw = true): ?object
            {
                throw new \LogicException('more IP detectors');
            }
        };
        $this->assertSame([200, 429], [$this->code($presenter), $this->code($presenter)]);
    }

    public function testABrokenLoggerDoesNotLoseTheResponseOfTheHandler(): void
    {
        $presenter = $this->presenter($this->limiter(new EndpointKey()), ipDetector: false);
        $error = new \RuntimeException('the detector is down');
        $presenter->context = new class ($error) extends Container {
            public function __construct(private readonly \RuntimeException $error)
            {
                parent::__construct();
            }

            #[\Override]
            public function findByType(string $type): array
            {
                return $type === ApiLoggerInterface::class ? ['logger'] : [];
            }

            #[\Override]
            public function getByType(string $type, bool $throw = true): ?object
            {
                return $type === ApiLoggerInterface::class
                    ? new class implements ApiLoggerInterface {
                        public function log(int $responseCode, string $requestMethod, string $requestHeader, string $requestUri, string $requestIp, string $requestAgent, int $responseTime): bool
                        {
                            return true;
                        }
                    }
                    : throw $this->error;
            }
        };
        $handler = $this->createMock(ErrorHandlerInterface::class);
        $handler->expects($this->never())->method('handle');
        $presenter->errorHandler = $handler;
        $tracy = new class implements ILogger {
            /** @var list<array{mixed, mixed}> */
            public array $logged = [];

            public function log(mixed $value, string $level = self::INFO): void
            {
                $this->logged[] = [$value, $level];
            }
        };
        $previous = Debugger::getLogger();
        Debugger::setLogger($tracy);
        try {
            $this->assertSame(200, $this->code($presenter), 'the error goes only to the log, the client gets the response of the handler');
        } finally {
            Debugger::setLogger($previous);
        }
        $this->assertSame(200, $presenter->response->getCode());
        $this->assertSame([[$error, ILogger::EXCEPTION]], $tracy->logged);
    }

    public function testABrokenTracyLoggerFallsBackToErrorLog(): void
    {
        $presenter = $this->presenter($this->limiter(new EndpointKey()), ipDetector: false);
        $presenter->context = new class extends Container {
            #[\Override]
            public function findByType(string $type): array
            {
                return $type === ApiLoggerInterface::class ? ['logger'] : [];
            }

            #[\Override]
            public function getByType(string $type, bool $throw = true): ?object
            {
                return $type === ApiLoggerInterface::class
                    ? new class implements ApiLoggerInterface {
                        public function log(int $responseCode, string $requestMethod, string $requestHeader, string $requestUri, string $requestIp, string $requestAgent, int $responseTime): bool
                        {
                            throw new \RuntimeException('the log is down');
                        }
                    }
                    : new IpDetector();
            }
        };
        $tracy = new class implements ILogger {
            public function log(mixed $value, string $level = self::INFO): void
            {
                throw new \RuntimeException('tracy is down');
            }
        };
        $file = tempnam(sys_get_temp_dir(), 'log');
        $previousLog = ini_set('error_log', (string) $file);
        $previous = Debugger::getLogger();
        Debugger::setLogger($tracy);
        try {
            $this->assertSame(200, $this->code($presenter));
        } finally {
            Debugger::setLogger($previous);
            ini_set('error_log', (string) $previousLog);
        }
        $this->assertStringContainsString('The request log failed: the log is down (the logger failed: tracy is down)', (string) file_get_contents((string) $file));
        unlink((string) $file);
    }

    public function testAnExceptionOfGetIdentityGoesToTheErrorHandler(): void
    {
        $authorization = new class implements ApiAuthorizationInterface, AuthorizationIdentityInterface {
            public function authorized(): bool
            {
                return true;
            }

            public function getErrorMessage(): ?string
            {
                return null;
            }

            public function getIdentity(): ?string
            {
                throw new \RuntimeException('the database is down');
            }
        };
        $presenter = $this->presenter($this->limiter(new EndpointKey()), ipDetector: false);
        $apiDecider = new ApiDecider(new Container());
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), $authorization, $this->limiter(new EndpointKey()));
        $presenter->apiDecider = $apiDecider;
        $handler = $this->createMock(ErrorHandlerInterface::class);
        $handler->expects($this->once())->method('handleAuthorizationException')->willReturn(new JsonApiResponse(500, ['status' => 'error']));
        $presenter->errorHandler = $handler;
        $this->assertSame(500, $this->code($presenter));
        $this->assertSame(500, $presenter->response->getCode());
    }

    public function testAnExceptionOfAnOwnLimiterGoesToTheErrorHandler(): void
    {
        $limiter = new class implements RateLimiterInterface {
            public function consume(RateLimitContext $context, int $cost = 1): array
            {
                throw new \LogicException('a bug in the limiter');
            }
        };
        $this->failingLimiterExpected($this->presenter($limiter, ipDetector: true), 'a bug in the limiter');
    }

    public function testLimitWithoutIpWorksWithoutIpDetector(): void
    {
        $presenter = $this->presenter($this->limiter(new EndpointKey()), ipDetector: false);
        $this->assertSame([200, 429], [$this->code($presenter), $this->code($presenter)], 'the limit without the IP is applied');
    }

    public function testAFailingLimiterAfterTheAuthorizationGivesTheParamsToTheErrorHandler(): void
    {
        $limiter = new class implements RateLimiterInterface {
            public function consume(RateLimitContext $context, int $cost = 1): array
            {
                if ($context->stage === RateLimitStage::AfterAuthorization) {
                    throw new \LogicException('a bug after the authorization');
                }
                return [];
            }
        };
        $presenter = $this->presenter($limiter, ipDetector: true);
        $apiDecider = new ApiDecider(new Container());
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new EchoHandler(), new NoAuthorization(), $limiter);
        $presenter->apiDecider = $apiDecider;
        $_GET['status'] = 'ok';
        $_GET['message'] = 'hi';
        try {
            $handler = $this->createMock(ErrorHandlerInterface::class);
            $handler->expects($this->once())->method('handle')
                ->with($this->isInstanceOf(\LogicException::class), ['status' => 'ok', 'message' => 'hi'])
                ->willReturn(new JsonApiResponse(500, []));
            $presenter->errorHandler = $handler;
            $this->assertSame(500, $this->code($presenter));
        } finally {
            unset($_GET['status'], $_GET['message']);
        }
    }

    public function testTheIpIsDetectedOncePerRequest(): void
    {
        $limiter = new class implements RateLimiterInterface {
            public function consume(RateLimitContext $context, int $cost = 1): array
            {
                $context->ip();
                return [];
            }
        };
        $presenter = $this->presenter($limiter, ipDetector: false);
        $detector = new class implements IpDetectorInterface {
            public int $calls = 0;

            public function getRequestIp(): string
            {
                $this->calls++;
                return '1.2.3.4';
            }
        };
        $presenter->context = new class ($detector) extends Container {
            public function __construct(private readonly IpDetectorInterface $detector)
            {
                parent::__construct();
            }

            #[\Override]
            public function findByType(string $type): array
            {
                return $type === ApiLoggerInterface::class ? ['logger'] : [];
            }

            #[\Override]
            public function getByType(string $type, bool $throw = true): ?object
            {
                return match ($type) {
                    IpDetectorInterface::class => $this->detector,
                    ApiLoggerInterface::class => new class implements ApiLoggerInterface {
                        public function log(int $responseCode, string $requestMethod, string $requestHeader, string $requestUri, string $requestIp, string $requestAgent, int $responseTime): bool
                        {
                            TestCase::assertSame('1.2.3.4', $requestIp);
                            TestCase::assertLessThan(60_000, $responseTime, 'the time of the request in ms');
                            return true;
                        }
                    },
                    default => null,
                };
            }
        };
        $this->assertSame(200, $this->code($presenter));
        $this->assertSame(1, $detector->calls, 'both stages and the log share one read');
        $this->assertSame(200, $this->code($presenter));
        $this->assertSame(2, $detector->calls, 'the next request reads it again');
    }

    public function testWithoutAnIpDetectorServiceTheIpIsNull(): void
    {
        $limiter = new class implements RateLimiterInterface {
            public function consume(RateLimitContext $context, int $cost = 1): array
            {
                TestCase::assertNull($context->ip());
                return [];
            }
        };
        $presenter = $this->presenter($limiter, ipDetector: false);
        $presenter->context = new Container();
        $this->assertSame(200, $this->code($presenter), 'a container without the detector is no error');
    }
}
