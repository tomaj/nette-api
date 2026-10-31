<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Nette\Application\Request;
use Nette\DI\Container;
use Nette\Http\Response as HttpResponse;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Error\ProblemDetailsErrorHandler;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\RateLimit\FixedWindowLimiter;
use Tomaj\NetteApi\RateLimit\Key\IdentityKey;
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tomaj\NetteApi\RateLimit\RateLimiterChain;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResult;
use Tomaj\NetteApi\RateLimit\RateLimitStage;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\TokenBucketLimiter;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;
use Tomaj\NetteApi\Test\Error\StaticConfigurator;
use Tomaj\NetteApi\Test\RateLimit\LimiterTest;

/**
 * Nette sends headers with header(); in CLI they are only observable through xdebug_get_headers().
 */
#[RequiresFunction('xdebug_get_headers')]
final class ApiPresenterRateLimitTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    private function presenter(RateLimiterInterface $rateLimit, bool $token = false, bool $problemDetails = false, bool $ipDetector = true): ApiPresenter
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

            public function getByType(string $type, bool $throw = true): ?object
            {
                return $type === IpDetectorInterface::class && $this->ipDetector ? new IpDetector() : null;
            }
        };
        $presenter->outputConfigurator = new StaticConfigurator(false);
        $presenter->errorHandler = $problemDetails ? new ProblemDetailsErrorHandler($presenter->outputConfigurator) : new DefaultErrorHandler($presenter->outputConfigurator);
        return $presenter;
    }

    /**
     * @return array{\Nette\Application\Response, list<string>}
     */
    private function call(ApiPresenter $presenter): array
    {
        $before = count(xdebug_get_headers());
        $result = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']));
        $headers = array_values(array_filter(
            array_slice(xdebug_get_headers(), $before),
            fn(string $header): bool => preg_match('~^(RateLimit|Retry-After)~i', $header) === 1,
        ));
        return [$result, $headers];
    }

    public function testHeadersAndTooManyRequests(): void
    {
        $presenter = $this->presenter(new FixedWindowLimiter('perIp', 2, 60, new IpKey(), new InMemoryStorage(), LimiterTest::clock(30.0)));

        [$result, $headers] = $this->call($presenter);
        $this->assertSame(200, $result->getCode());
        $this->assertSame(['RateLimit-Policy: "perIp";q=2;w=60', 'RateLimit: "perIp";r=1;t=30'], $headers);

        $this->call($presenter);
        [$result, $headers] = $this->call($presenter);
        $this->assertInstanceOf(JsonApiResponse::class, $result);
        $this->assertSame(429, $result->getCode());
        $this->assertSame(429, $presenter->response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'Too many requests. Retry after 30 seconds.'], $result->getPayload());
        $this->assertSame(['RateLimit-Policy: "perIp";q=2;w=60', 'RateLimit: "perIp";r=0;t=30', 'Retry-After: 30'], $headers);
    }

    public function testProblemDetails(): void
    {
        $presenter = $this->presenter(new FixedWindowLimiter('perIp', 1, 60, new IpKey(), new InMemoryStorage(), LimiterTest::clock(0.0)), problemDetails: true);
        $this->call($presenter);
        [$result] = $this->call($presenter);
        $this->assertInstanceOf(ProblemDetailsResponse::class, $result);
        $this->assertSame(429, $result->getCode());
        $this->assertSame(['limit' => 1, 'retryAfter' => 60], $result->getProblem()->extensions);
    }

    public function testBeforeAndAfterAuthorization(): void
    {
        $storage = new InMemoryStorage();
        $presenter = $this->presenter(new RateLimiterChain(
            new FixedWindowLimiter('perIp', 3, 60, new IpKey(), $storage, LimiterTest::clock(0.0)),
            new TokenBucketLimiter('perUser', 1, 10, new IdentityKey(), $storage, LimiterTest::clock(0.0)),
        ), token: true);

        [$result, $headers] = $this->call($presenter);
        $this->assertSame(401, $result->getCode(), 'no token: only the ip limit');
        $this->assertSame(['RateLimit-Policy: "perIp";q=3;w=60', 'RateLimit: "perIp";r=2;t=60'], $headers);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
        [$result, $headers] = $this->call($presenter);
        $this->assertSame(200, $result->getCode());
        $this->assertSame([
            'RateLimit-Policy: "perIp";q=3;w=60', 'RateLimit: "perIp";r=1;t=60',
            'RateLimit-Policy: "perUser";q=1;w=10', 'RateLimit: "perUser";r=0;t=10',
        ], $headers);

        [$result, $headers] = $this->call($presenter);
        $this->assertSame(429, $result->getCode(), 'the user limit rejects after the authorization');
        $this->assertSame('Retry-After: 10', $headers[4]);

        [$result, $headers] = $this->call($presenter);
        $this->assertSame(429, $result->getCode(), 'the ip limit rejects before the authorization');
        $this->assertSame(['RateLimit-Policy: "perIp";q=3;w=60', 'RateLimit: "perIp";r=0;t=60', 'Retry-After: 60'], $headers);
    }

    private function fixed(RateLimitResult ...$results): RateLimiterInterface
    {
        return new readonly class ($results) implements RateLimiterInterface {
            /**
             * @param list<RateLimitResult> $results
             */
            public function __construct(private array $results)
            {
            }

            public function consume(RateLimitContext $context, int $cost = 1): array
            {
                return $context->stage === RateLimitStage::BeforeAuthorization ? $this->results : [];
            }
        };
    }

    public function testLongestRetryAfterWinsAndOwnResponse(): void
    {
        $own = new JsonApiResponse(429, ['own' => true]);
        [$result, $headers] = $this->call($this->presenter($this->fixed(
            new RateLimitResult(false, 10, 0, 60, 5, 5, 'a'),
            new RateLimitResult(false, 20, 0, 0, 50, 50, 'b', $own),
            new RateLimitResult(false, 30, 0, 60, 7, 7, 'c'),
            new RateLimitResult(true, 40, 9, 60, 7, null, 'd'),
        )));
        $this->assertSame($own, $result);
        $this->assertSame([
            'RateLimit-Policy: "a";q=10;w=60', 'RateLimit: "a";r=0;t=5',
            'RateLimit-Policy: "b";q=20', 'RateLimit: "b";r=0;t=50',
            'RateLimit-Policy: "c";q=30;w=60', 'RateLimit: "c";r=0;t=7',
            'RateLimit-Policy: "d";q=40;w=60', 'RateLimit: "d";r=9;t=7',
            'Retry-After: 50',
        ], $headers);
    }

    public function testRejectedWithoutRetryAfter(): void
    {
        [$result, $headers] = $this->call($this->presenter($this->fixed(new RateLimitResult(false, 10, 0, 60, 0, null, 'a'))));
        $this->assertSame(429, $result->getCode());
        $this->assertInstanceOf(JsonApiResponse::class, $result);
        $this->assertSame(['status' => 'error', 'message' => 'Too many requests.'], $result->getPayload());
        $this->assertSame(['RateLimit-Policy: "a";q=10;w=60', 'RateLimit: "a";r=0;t=0'], $headers);
    }

    public function testUnknownResetTimeIsLeftOut(): void
    {
        [, $headers] = $this->call($this->presenter($this->fixed(new RateLimitResult(true, 10, 4, 0, null, null, 'a'))));
        $this->assertSame(['RateLimit-Policy: "a";q=10', 'RateLimit: "a";r=4'], $headers);
    }

    public function testPolicyOfAnOwnLimiterCannotBreakTheHeaders(): void
    {
        [, $headers] = $this->call($this->presenter($this->fixed(new RateLimitResult(false, 10, 0, 60, 1, -5, "a\"b\\c\r\nX-Injected: 1\xC3\xA1"))));
        $this->assertSame(['RateLimit-Policy: "a\\"b\\\\cX-Injected: 1";q=10;w=60', 'RateLimit: "a\\"b\\\\cX-Injected: 1";r=0;t=1', 'Retry-After: 0'], $headers);
    }

    public function testNegativeNumbersOfAnOwnLimiterAreSentAsZero(): void
    {
        $presenter = $this->presenter($this->fixed(new RateLimitResult(false, -1, -2, 60, -3, -5, 'p')), problemDetails: true);
        $handler = new class ($presenter->errorHandler) implements ErrorHandlerInterface {
            public ?RateLimitResponse $rateLimit = null;

            public function __construct(private readonly ErrorHandlerInterface $inner)
            {
            }

            public function handle(\Throwable $exception, array $params): ResponseInterface
            {
                return $this->inner->handle($exception, $params);
            }

            public function handleInputParams(array $errors): ResponseInterface
            {
                return $this->inner->handleInputParams($errors);
            }

            public function handleSchema(array $errors, array $params): ResponseInterface
            {
                return $this->inner->handleSchema($errors, $params);
            }

            public function handleAuthorization(ApiAuthorizationInterface $auth, array $params): ResponseInterface
            {
                return $this->inner->handleAuthorization($auth, $params);
            }

            public function handleAuthorizationException(\Throwable $exception, array $params): ResponseInterface
            {
                return $this->inner->handleAuthorizationException($exception, $params);
            }

            public function handleNotFound(EndpointInterface $endpoint): ResponseInterface
            {
                return $this->inner->handleNotFound($endpoint);
            }

            public function handleRateLimit(RateLimitResponse $rateLimit): ResponseInterface
            {
                $this->rateLimit = $rateLimit;
                return $this->inner->handleRateLimit($rateLimit);
            }
        };
        $presenter->errorHandler = $handler;
        [$result, $headers] = $this->call($presenter);
        $this->assertSame(['RateLimit-Policy: "p";q=0;w=60', 'RateLimit: "p";r=0;t=0', 'Retry-After: 0'], $headers);
        $this->assertInstanceOf(ProblemDetailsResponse::class, $result);
        $this->assertSame([0, 0, 0], [$handler->rateLimit?->getLimit(), $handler->rateLimit?->getRemaining(), $handler->rateLimit?->getRetryAfter()]);
        $this->assertSame('Too many requests. Retry after 0 seconds.', $result->getPayload()->detail);
    }

    public function testFirstOfEqualRetryAfterWins(): void
    {
        $first = new JsonApiResponse(429, ['first' => true]);
        $second = new JsonApiResponse(429, ['second' => true]);
        [$result] = $this->call($this->presenter($this->fixed(
            new RateLimitResult(false, 10, 0, 60, 5, 5, 'a', $first),
            new RateLimitResult(false, 20, 0, 60, 5, 5, 'b', $second),
        )));
        $this->assertSame($first, $result);
    }
}
