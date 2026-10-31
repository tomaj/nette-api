<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

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

/**
 * The IP limits without an IpDetectorInterface service; only response codes, so it runs without xdebug too
 */
final class ApiPresenterIpDetectorTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
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
        $this->expectExceptionObject(new \LogicException(
            'The rate limit of endpoint GET v1/test/api is keyed by ip, but the container has no Tomaj\NetteApi\Misc\IpDetectorInterface service, so the client IP is not known. '
            . 'Register one, for example services: - Tomaj\NetteApi\Misc\IpDetector (or your own detector reading the address set by your proxy), or use another key.',
        ));
        $this->code($this->presenter($this->limiter(new IpKey()), ipDetector: false));
    }

    public function testIpLimitAfterTheAuthorizationWithoutIpDetectorFails(): void
    {
        $presenter = $this->presenter($this->limiter(new CompositeKey(new IpKey(), new IdentityKey())), ipDetector: false, token: true);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is keyed by ip');
        $this->code($presenter);
    }

    public function testLimitWithoutIpWorksWithoutIpDetector(): void
    {
        $presenter = $this->presenter($this->limiter(new EndpointKey()), ipDetector: false);
        $this->assertSame([200, 429], [$this->code($presenter), $this->code($presenter)], 'the limit without the IP is applied');
    }
}
