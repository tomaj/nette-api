<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit;

use Nette\Application\Responses\TextResponse;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\FixedWindowLimiter;
use Tomaj\NetteApi\RateLimit\Key\IdentityKey;
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\LegacyRateLimitAdapter;
use Tomaj\NetteApi\RateLimit\NoRateLimit;
use Tomaj\NetteApi\RateLimit\RateLimiterChain;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\RateLimit\RateLimitResult;
use Tomaj\NetteApi\RateLimit\RateLimitStage;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;

final class ChainAndLegacyTest extends TestCase
{
    public function testChainStopsAtTheFirstRejection(): void
    {
        $storage = new InMemoryStorage();
        $ip = new FixedWindowLimiter('ip', 1, 60, new IpKey(), $storage, LimiterTest::clock(0.0));
        $ip2 = new FixedWindowLimiter('ip2', 3, 60, new IpKey(), $storage, LimiterTest::clock(0.0));
        $user = new FixedWindowLimiter('user', 2, 60, new IdentityKey(), $storage, LimiterTest::clock(0.0));
        $chain = new RateLimiterChain($ip, $ip2, $user);
        $this->assertSame([$ip, $ip2, $user], $chain->getLimiters());

        $before = $chain->consume(ContextFactory::create());
        $this->assertSame(['ip', 'ip2'], array_map(fn(RateLimitResult $r): string => $r->policy, $before));
        $after = $chain->consume(ContextFactory::create(RateLimitStage::AfterAuthorization, identity: 'u'));
        $this->assertSame(['user'], array_map(fn(RateLimitResult $r): string => $r->policy, $after));

        $again = $chain->consume(ContextFactory::create());
        $this->assertSame(['ip'], array_map(fn(RateLimitResult $r): string => $r->policy, $again));
        $this->assertFalse($again[0]->accepted);
        $this->assertSame(1, $ip2->consume(ContextFactory::create())[0]->remaining, 'a rejected request does not use up the next limits');
        $this->assertSame([], new RateLimiterChain()->consume(ContextFactory::create()));
    }

    private function legacy(?RateLimitResponse $response): RateLimitInterface
    {
        return new class ($response) implements RateLimitInterface {
            public int $calls = 0;

            public function __construct(private readonly ?RateLimitResponse $response)
            {
            }

            public function check(): ?RateLimitResponse
            {
                $this->calls++;
                return $this->response;
            }
        };
    }

    public function testWrap(): void
    {
        $limiter = new NoRateLimit();
        $this->assertSame($limiter, LegacyRateLimitAdapter::wrap($limiter), 'NoRateLimit implements both, it is not wrapped');
        $legacy = $this->legacy(null);
        $adapter = @LegacyRateLimitAdapter::wrap($legacy);
        $this->assertInstanceOf(LegacyRateLimitAdapter::class, $adapter);
        $this->assertSame($legacy, $adapter->getRateLimit());
    }

    public function testDeprecation(): void
    {
        $legacy = $this->legacy(null);
        $errors = [];
        set_error_handler(function (int $type, string $message) use (&$errors): bool {
            $errors[] = [$type, $message];
            return true;
        });
        try {
            new LegacyRateLimitAdapter($legacy);
        } finally {
            restore_error_handler();
        }
        $this->assertSame([[E_USER_DEPRECATED, LegacyRateLimitAdapter::deprecation($legacy::class)]], $errors);
        $this->assertSame(
            'Foo implements Tomaj\NetteApi\RateLimit\RateLimitInterface, which is deprecated since nette-api 4.0 and will be removed in 5.0; it still works through Tomaj\NetteApi\RateLimit\LegacyRateLimitAdapter. '
            . 'Replace it with a built-in limiter in neon, for example api: {rateLimits: {perIp: {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}}}, '
            . 'or implement Tomaj\NetteApi\RateLimit\RateLimiterInterface::consume(RateLimitContext $context, int $cost = 1): array returning a list of RateLimitResult. See UPGRADE-4.0.md, section Rate limiting.',
            LegacyRateLimitAdapter::deprecation('Foo'),
        );
    }

    public function testAdapter(): void
    {
        $this->assertSame([], @new LegacyRateLimitAdapter($this->legacy(null))->consume(ContextFactory::create()));

        $this->assertEquals(
            [new RateLimitResult(true, 10, 4, 0, null, null, 'default')],
            @new LegacyRateLimitAdapter($this->legacy(new RateLimitResponse(10, 4)))->consume(ContextFactory::create()),
        );

        $error = new TextResponse('slow down');
        $legacy = $this->legacy(new RateLimitResponse(10, 0, 30, $error));
        $adapter = @new LegacyRateLimitAdapter($legacy);
        $this->assertEquals([new RateLimitResult(false, 10, 0, 0, 30, 30, 'default', $error)], $adapter->consume(ContextFactory::create()));
        $this->assertSame([], $adapter->consume(ContextFactory::create(RateLimitStage::AfterAuthorization)), 'checked only before the authorization like in 3.x');
        $this->assertSame(1, $legacy->calls);
    }

    public function testNoRateLimit(): void
    {
        $limiter = new NoRateLimit();
        $this->assertSame([], $limiter->consume(ContextFactory::create()));
        $this->assertNull($limiter->check());
    }

    public function testLimitersAreAList(): void
    {
        $limiter = new NoRateLimit();
        $this->assertSame([$limiter, $limiter], new RateLimiterChain(...['a' => $limiter, 'b' => $limiter])->getLimiters());
    }
}
