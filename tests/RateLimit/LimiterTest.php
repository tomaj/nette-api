<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit;

use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Algorithm\SlidingWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Tomaj\NetteApi\RateLimit\FixedWindowLimiter;
use Tomaj\NetteApi\RateLimit\Key\IdentityKey;
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\RateLimitResult;
use Tomaj\NetteApi\RateLimit\RateLimitStage;
use Tomaj\NetteApi\RateLimit\SlidingWindowLimiter;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageException;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;
use Tomaj\NetteApi\RateLimit\StorageRateLimiter;
use Tomaj\NetteApi\RateLimit\SystemClock;
use Tomaj\NetteApi\RateLimit\TokenBucketLimiter;
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tracy\Debugger;
use Tracy\ILogger;

final class LimiterTest extends TestCase
{
    public static function clock(float $now): ClockInterface
    {
        return new readonly class ($now) implements ClockInterface {
            public function __construct(private float $now)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $this->now)) ?: throw new \LogicException();
            }
        };
    }

    /**
     * @return iterable<string, array{class-string<StorageRateLimiter>, string}>
     */
    public static function limiters(): iterable
    {
        yield 'fixed' => [FixedWindowLimiter::class, FixedWindow::class];
        yield 'sliding' => [SlidingWindowLimiter::class, SlidingWindow::class];
        yield 'bucket' => [TokenBucketLimiter::class, TokenBucket::class];
    }

    /**
     * @param class-string<StorageRateLimiter> $class
     */
    #[DataProvider('limiters')]
    public function testCallsItsAlgorithm(string $class, string $algorithm): void
    {
        $storage = new class implements RateLimitStorageInterface {
            /** @var list<array<mixed>> */
            public array $calls = [];

            public function consume(RateLimitAlgorithmInterface $algorithm, string $key, int $limit, int $interval, int $cost, float $now): StorageResult
            {
                $this->calls[] = [$algorithm::class, $key, $limit, $interval, $cost, $now];
                return new StorageResult(false, 3, 40, 7);
            }
        };
        $limiter = new $class('per-ip.v1', 10, '1 minute', new IpKey(), $storage, self::clock(1000.25));

        $this->assertSame('per-ip.v1', $limiter->getPolicy());
        $this->assertSame(10, $limiter->getLimit());
        $this->assertSame(60, $limiter->getInterval());
        $this->assertInstanceOf(IpKey::class, $limiter->getKey());
        $this->assertEquals([new RateLimitResult(false, 10, 3, 60, 40, 7, 'per-ip.v1')], $limiter->consume(ContextFactory::create(), 2));
        $this->assertSame([[$algorithm, 'per-ip.v1:ip:1.2.3.4', 10, 60, 2, 1000.25]], $storage->calls);
    }

    public function testCountsInStorage(): void
    {
        $limiter = new FixedWindowLimiter('p', 2, 60, new IpKey(), new InMemoryStorage(), self::clock(0.0));
        $context = ContextFactory::create();
        $this->assertTrue($limiter->consume($context)[0]->accepted);
        $this->assertTrue($limiter->consume($context)[0]->accepted);
        $this->assertFalse($limiter->consume($context)[0]->accepted);
        $this->assertTrue($limiter->consume(ContextFactory::create(ip: '5.6.7.8'))[0]->accepted);
        $this->assertSame([], $limiter->consume(ContextFactory::create(ip: null)), 'no key, no limit');
    }

    public function testHighestLimit(): void
    {
        $limiter = new FixedWindowLimiter('p', 1_000_000_000, 60, new IpKey(), new InMemoryStorage(), self::clock(0.0));
        $result = $limiter->consume(ContextFactory::create(), 1_000_000_000)[0];
        $this->assertTrue($result->accepted);
        $this->assertSame(0, $result->remaining);
    }

    public function testStages(): void
    {
        $byIp = new FixedWindowLimiter('ip', 5, 60, new IpKey(), new InMemoryStorage());
        $this->assertCount(1, $byIp->consume(ContextFactory::create(RateLimitStage::BeforeAuthorization)));
        $this->assertSame([], $byIp->consume(ContextFactory::create(RateLimitStage::AfterAuthorization, identity: 'u')));

        $byIdentity = new FixedWindowLimiter('user', 5, 60, new IdentityKey(), new InMemoryStorage());
        $this->assertSame([], $byIdentity->consume(ContextFactory::create(RateLimitStage::BeforeAuthorization)));
        $this->assertCount(1, $byIdentity->consume(ContextFactory::create(RateLimitStage::AfterAuthorization, identity: 'u')));
        $this->assertSame([], $byIdentity->consume(ContextFactory::create(RateLimitStage::AfterAuthorization)), 'no identity, no limit');
    }

    public function testSystemClockIsDefault(): void
    {
        $storage = new InMemoryStorage();
        $limiter = new TokenBucketLimiter('p', 1, 3600, new IpKey(), $storage);
        $before = microtime(true);
        $limiter->consume(ContextFactory::create());
        $result = $storage->consume(new TokenBucket(), 'p:ip:1.2.3.4', 1, 3600, 1, $before);
        $this->assertFalse($result->accepted, 'the limiter counted at the current time');

        $now = new SystemClock()->now();
        $this->assertEqualsWithDelta(microtime(true), (float) $now->format('U.u'), 1.0);
    }

    /**
     * @return iterable<array{string, int, int|string, int, string}>
     */
    public static function invalid(): iterable
    {
        $class = FixedWindowLimiter::class;
        yield ['per ip', 5, 60, 1, "{$class}: the policy name 'per ip' is not valid, it is sent in the RateLimit-Policy header and used in the storage key. Use only letters, digits, '_', '-' and '.', for example 'perIp'."];
        yield ['', 5, 60, 1, "{$class}: the policy name '' is not valid"];
        yield ['p"', 5, 60, 1, "{$class}: the policy name 'p\"' is not valid"];
        yield ["p\xC3\xA1", 5, 60, 1, "the policy name 'p\xC3\xA1' is not valid"];
        yield ["p\n", 5, 60, 1, "the policy name 'p\n' is not valid"];
        yield ['p', 1_000_000_001, 60, 1, "{$class} 'p': the limit 1000000001 is more than 1000000000. Use at most 1000000000 requests per interval, the storages count in floating point numbers and lose precision above it."];
        yield ['p', 0, 60, 1, "{$class} 'p': the limit 0 is less than 1. Use the number of requests allowed per interval, for example 60; to turn the limit off, use rateLimit: false on the endpoint."];
        yield ['p', 5, 'often', 1, "{$class} 'p': 'often' is not an interval."];
        yield ['p', 5, 0, 1, "{$class} 'p': '0' is shorter than 1 second."];
    }

    #[DataProvider('invalid')]
    public function testInvalid(string $policy, int $limit, int|string $interval, int $cost, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        new FixedWindowLimiter($policy, $limit, $interval, new IpKey(), new InMemoryStorage());
    }

    public function testInvalidCost(): void
    {
        $limiter = new FixedWindowLimiter('p', 5, 60, new IpKey(), new InMemoryStorage());
        $this->assertCount(1, $limiter->consume(ContextFactory::create(), 5));
        foreach ([0, 6] as $cost) {
            try {
                $limiter->consume(ContextFactory::create(), $cost);
                $this->fail('an exception expected');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame("Rate limit 'p': the cost {$cost} must be between 1 and the limit 5, a cost above the limit would never pass. Pass a cost from 1 up to the limit to consume(), or raise the limit of the policy.", $e->getMessage());
            }
        }
    }

    private function brokenStorage(): RateLimitStorageInterface
    {
        return new class implements RateLimitStorageInterface {
            public function consume(RateLimitAlgorithmInterface $algorithm, string $key, int $limit, int $interval, int $cost, float $now): StorageResult
            {
                throw $algorithm instanceof TokenBucket ? new \LogicException('a bug is not hidden') : new RateLimitStorageException('Redis is down');
            }
        };
    }

    public function testFailOpenLogsWarning(): void
    {
        $logger = new class implements ILogger {
            /** @var list<array{mixed, string}> */
            public array $logs = [];

            public function log(mixed $value, string $level = self::INFO): void
            {
                $this->logs[] = [$value, $level];
            }
        };
        $limiter = new SlidingWindowLimiter('p', 5, 60, new IpKey(), $this->brokenStorage(), null, $logger);
        $this->assertSame([], $limiter->consume(ContextFactory::create()));
        $this->assertSame([["Rate limit 'p' is skipped, the request passed without a limit: Redis is down", ILogger::WARNING]], $logger->logs);
    }

    public function testFailOpenLogsToTracyByDefault(): void
    {
        $logger = new class implements ILogger {
            /** @var list<array{mixed, string}> */
            public array $logs = [];

            public function log(mixed $value, string $level = self::INFO): void
            {
                $this->logs[] = [$value, $level];
            }
        };
        Debugger::setLogger($logger);
        $limiter = new FixedWindowLimiter('p', 5, 60, new IpKey(), $this->brokenStorage());
        $this->assertSame([], $limiter->consume(ContextFactory::create()));
        $this->assertSame([["Rate limit 'p' is skipped, the request passed without a limit: Redis is down", ILogger::WARNING]], $logger->logs);
    }

    public function testFailOpenSurvivesABrokenLogger(): void
    {
        $logger = new class implements ILogger {
            public function log(mixed $value, string $level = self::INFO): void
            {
                throw new \LogicException('no log directory');
            }
        };
        $log = tempnam(sys_get_temp_dir(), 'rate-limit');
        $previous = ini_set('error_log', (string) $log);
        try {
            $limiter = new SlidingWindowLimiter('p', 5, 60, new IpKey(), $this->brokenStorage(), null, $logger);
            $this->assertSame([], $limiter->consume(ContextFactory::create()), 'the request still passes');
            $this->assertStringContainsString("Rate limit 'p' is skipped, the request passed without a limit: Redis is down (the logger failed: no log directory)", (string) file_get_contents((string) $log));
        } finally {
            ini_set('error_log', (string) $previous);
            @unlink((string) $log);
        }
    }

    public function testLongKeysAreHashed(): void
    {
        $storage = new InMemoryStorage();
        $long = new class implements RateLimitKeyInterface {
            public string $key = '';

            public function key(RateLimitContext $context): ?string
            {
                return $this->key;
            }

            public function needsIdentity(): bool
            {
                return false;
            }
        };
        $limiter = new FixedWindowLimiter('p', 5, 60, $long, $storage);
        $long->key = str_repeat('a', StorageRateLimiter::MAX_KEY_LENGTH);
        $limiter->consume(ContextFactory::create());
        $long->key .= 'b';
        $limiter->consume(ContextFactory::create());
        $keys = array_keys((array) new \ReflectionProperty($storage, 'values')->getValue($storage));
        $this->assertSame(['p:' . str_repeat('a', 200), 'p:sha256:' . hash('sha256', str_repeat('a', 200) . 'b')], $keys);
    }

    public function testOtherErrorsAreNotHidden(): void
    {
        $limiter = new TokenBucketLimiter('p', 5, 60, new IpKey(), $this->brokenStorage());
        $this->expectException(\LogicException::class);
        $limiter->consume(ContextFactory::create());
    }
}
