<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tomaj\NetteApi\RateLimit\Redis\PhpRedisClient;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;

#[RequiresPhpExtension('redis')]
final class PhpRedisStorageTest extends RedisTestCase
{
    /**
     * Declared as object, a return type \Redis would be a fatal error while loading the class without the extension,
     * before RequiresPhpExtension can skip the test
     *
     * @return \Redis
     */
    #[\Override]
    protected function client(): object
    {
        try {
            return self::phpRedis();
        } catch (\RedisException $e) {
            $this->markTestSkipped('Redis is not reachable: ' . $e->getMessage());
        }
    }

    public function testWrappedClientWorksToo(): void
    {
        $storage = new RedisStorage(new PhpRedisClient($this->client()), $this->prefix);
        $this->assertSame(2, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 1, 0.0)->remaining);
    }

    public function testScriptErrorIsReported(): void
    {
        $client = new PhpRedisClient($this->client());
        try {
            $client->evaluate('return redis.error_reply("NOPE")', [$this->prefix . 'x'], []);
            $this->fail('an error expected');
        } catch (\RuntimeException $e) {
            $this->assertMatchesRegularExpression('~^Redis EVAL failed: .*NOPE~i', $e->getMessage());
        }
        $this->assertFalse($client->evaluate('return nil', [$this->prefix . 'x'], []), 'the last error is cleared, a nil reply is not an error');
    }

    /**
     * @return iterable<string, array{int, mixed}>
     */
    public static function clientOptions(): iterable
    {
        yield 'prefix' => [\Redis::OPT_PREFIX, null];
        yield 'php serializer' => [\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP];
        yield 'json serializer' => [\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON];
        yield 'literal replies' => [\Redis::OPT_REPLY_LITERAL, true];
    }

    #[DataProvider('clientOptions')]
    public function testClientOptionsDoNotChangeTheCounters(int $option, mixed $value): void
    {
        $client = $this->client();
        // the prefix of the client goes first, so the keys still start with the prefix of the test
        $this->assertTrue($client->setOption($option, $value ?? $this->prefix));
        $storage = new RedisStorage($client, $value === null ? 'own:' : $this->prefix);
        foreach ([new FixedWindow(), new TokenBucket()] as $algorithm) {
            $this->assertSame([true, true, true, false], array_map(fn (): bool => $storage->consume($algorithm, $algorithm::class, 3, 60, 1, 0.0)->accepted, [1, 2, 3, 4]));
        }
    }

    public function testRedisClusterIsAccepted(): void
    {
        $cluster = new \ReflectionClass(\RedisCluster::class)->newInstanceWithoutConstructor();
        $this->assertInstanceOf(RedisStorage::class, new RedisStorage($cluster));
    }
}
