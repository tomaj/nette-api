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
        $storage->consume(new SlidingWindow(), 'sliding', 3, 60, 1, (float) time());
        $storage->consume(new TokenBucket(), 'bucket', 3, 60, 1, (float) time());
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('the TTL is read with ext-redis');
        }
        $redis = self::phpRedis();
        $keys = $redis->keys($this->prefix . '*');
        sort($keys);
        $this->assertSame([$this->prefix . '{bucket}', $this->prefix . '{fixed}', $this->prefix . '{sliding}'], $keys, 'one key of every counter, the key given to the script');
        foreach ($keys as $key) {
            $this->assertSame(\Redis::REDIS_HASH, $redis->type($key), 'the whole state is one hash');
            $ttl = $redis->ttl($key);
            $this->assertGreaterThan(0, $ttl);
            $this->assertLessThanOrEqual(122, $ttl);
        }
        $this->assertSame(['count', 'start'], self::fields($redis, $this->prefix . '{fixed}'));
        $this->assertSame(['curr', 'prev', 'start'], self::fields($redis, $this->prefix . '{sliding}'));
        $this->assertSame(['time', 'tokens'], self::fields($redis, $this->prefix . '{bucket}'));
    }

    /**
     * @return list<string>
     */
    private static function fields(\Redis $redis, string $key): array
    {
        $fields = $redis->hKeys($key);
        sort($fields);
        return $fields;
    }

    /**
     * The scripts write only the key they get, also when the windows move, the clocks differ and requests are rejected
     */
    #[DataProvider('luaAlgorithms')]
    public function testScriptWritesOnlyTheDeclaredKey(RateLimitAlgorithmInterface $algorithm): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('the keys are read with ext-redis');
        }
        $storage = $this->storage();
        $now = (float) time();
        foreach ([0, 0.5, 1, 1, 1, 30, -20, 61, 62, 200] as $shift) {
            $storage->consume($algorithm, 'only', 2, 60, 1, $now + $shift);
        }
        $this->assertSame([$this->prefix . '{only}'], self::phpRedis()->keys($this->prefix . '*'));
    }

    /**
     * A policy whose algorithm changes reads no foreign state, in Lua like in PHP
     */
    public function testLuaCountsLikePhpWhenTheAlgorithmChanges(): void
    {
        $php = new InMemoryStorage();
        $redis = $this->storage();
        $algorithms = [new FixedWindow(), new SlidingWindow(), new TokenBucket()];
        mt_srand(238);
        $now = 1_000_000.0;
        for ($i = 0; $i < 300; $i++) {
            $now += mt_rand(0, 8_000) / 1000;
            $algorithm = $algorithms[mt_rand(0, 2)];
            $cost = mt_rand(1, 3);
            $expected = $php->consume($algorithm, 'k', 5, 30, $cost, $now);
            $actual = $redis->consume($algorithm, 'k', 5, 30, $cost, $now);
            $this->assertEquals($expected, $actual, sprintf('request %d at %.3F, %s, cost %d', $i, $now, $algorithm::class, $cost));
        }
        mt_srand();
    }

    /**
     * Servers with clocks behind and ahead of each other: the newer window wins in Lua like in PHP
     */
    #[DataProvider('luaAlgorithms')]
    public function testLuaHandlesSkewedClocksLikePhp(RateLimitAlgorithmInterface $algorithm): void
    {
        $php = new InMemoryStorage();
        $redis = $this->storage();
        $now = 1_000_020.0;
        foreach ([0, 0, 45, -10, -10, 50, 49.5, 100, 40, 41, 160, 100, 220] as $i => $shift) {
            $expected = $php->consume($algorithm, 'skew', 3, 30, 1, $now + $shift);
            $actual = $redis->consume($algorithm, 'skew', 3, 30, 1, $now + $shift);
            $this->assertEquals($expected, $actual, sprintf('request %d at %+.1F', $i, $shift));
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
