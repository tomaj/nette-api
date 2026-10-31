<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Algorithm\SlidingWindow;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;

/**
 * Runs against the Redis in REDIS_HOST / REDIS_PORT (127.0.0.1:6379 by default), skipped when it is not reachable.
 * Every test uses its own key prefix and removes its keys.
 */
abstract class RedisTestCase extends StorageTestCase
{
    protected string $prefix;

    #[\Override]
    protected function setUp(): void
    {
        $this->prefix = 'nette-api-test:' . bin2hex(random_bytes(6)) . ':';
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (!extension_loaded('redis')) {
            return;
        }
        try {
            $redis = self::phpRedis();
        } catch (\RedisException) {
            return;
        }
        $iterator = null;
        while (($keys = $redis->scan($iterator, $this->prefix . '*', 100)) !== false) {
            if ($keys !== []) {
                $redis->del($keys);
            }
            if ($iterator === 0) {
                break;
            }
        }
    }

    abstract protected function client(): object;

    #[\Override]
    protected function storage(): RateLimitStorageInterface
    {
        return new RedisStorage($this->client(), $this->prefix);
    }

    protected static function host(): string
    {
        return getenv('REDIS_HOST') ?: '127.0.0.1';
    }

    protected static function port(): int
    {
        return (int) (getenv('REDIS_PORT') ?: 6379);
    }

    protected static function phpRedis(): \Redis
    {
        $redis = new \Redis();
        $redis->connect(self::host(), self::port(), 1.0);
        return $redis;
    }

    /**
     * @return iterable<string, array{RateLimitAlgorithmInterface}>
     */
    public static function luaAlgorithms(): iterable
    {
        yield 'fixed window' => [new FixedWindow()];
        yield 'sliding window' => [new SlidingWindow()];
        yield 'token bucket' => [new TokenBucket()];
    }

    /**
     * The Lua script and the PHP algorithm give the same results for the same random requests
     */
    #[DataProvider('luaAlgorithms')]
    public function testLuaCountsLikePhp(RateLimitAlgorithmInterface $algorithm): void
    {
        $redis = $this->storage();
        $php = new InMemoryStorage();
        mt_srand(195);
        $now = 1_000_000.0;
        for ($i = 0; $i < 400; $i++) {
            $now += mt_rand(0, 15_000) / 1000;
            $key = 'k' . mt_rand(1, 2);
            $cost = mt_rand(1, 4);
            $expected = $php->consume($algorithm, $key, 5, 30, $cost, $now);
            $actual = $redis->consume($algorithm, $key, 5, 30, $cost, $now);
            $this->assertEquals($expected, $actual, sprintf('request %d at %.3F, key %s, cost %d', $i, $now, $key, $cost));
        }
        mt_srand();
    }

    public function testCountersExpireInRedis(): void
    {
        $storage = $this->storage();
        $storage->consume(new FixedWindow(), 'fixed', 3, 60, 1, (float) time());
        $storage->consume(new TokenBucket(), 'bucket', 3, 60, 1, (float) time());
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('the TTL is read with ext-redis');
        }
        $redis = self::phpRedis();
        $keys = $redis->keys($this->prefix . '*');
        $this->assertCount(3, $keys, 'the counter of the window, the start of the newest window and the bucket');
        foreach ($keys as $key) {
            $this->assertStringStartsWith($this->prefix . '{', $key, 'a hash tag keeps the counters of one key in one cluster slot');
            $ttl = $redis->ttl($key);
            $this->assertGreaterThan(0, $ttl);
            $this->assertLessThanOrEqual(61, $ttl);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function algorithmNames(): iterable
    {
        yield 'fixedWindow' => ['fixedWindow'];
        yield 'slidingWindow' => ['slidingWindow'];
        yield 'tokenBucket' => ['tokenBucket'];
    }

    /**
     * 8 processes send 25 requests each at the same moment, the Lua script lets exactly 50 of the 200 through
     */
    #[DataProvider('algorithmNames')]
    public function testParallelClientsNeverExceedTheLimit(string $algorithm): void
    {
        $this->client();
        $type = $this instanceof PredisStorageTest ? 'predis' : 'phpredis';
        $this->assertSame(50, ParallelClients::run($type, self::host() . ':' . self::port(), $this->prefix, $algorithm));
    }
}
