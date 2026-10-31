<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\Test\RateLimit\Algorithm\OnlyInPhp;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\SlidingWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\Redis\RedisClientInterface;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageException;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;

/**
 * RedisStorage without a Redis server
 */
final class RedisStorageTest extends TestCase
{
    /**
     * @param mixed $reply
     */
    private function client(mixed $reply, ?\Throwable $error = null): RedisClientInterface
    {
        return new class ($reply, $error) implements RedisClientInterface {
            /** @var list<array{string, list<string>, list<int|string>}> */
            public array $calls = [];

            public function __construct(private readonly mixed $reply, private readonly ?\Throwable $error)
            {
            }

            public function evaluate(string $script, array $keys, array $arguments): mixed
            {
                $this->calls[] = [$script, $keys, $arguments];
                if ($this->error !== null) {
                    throw $this->error;
                }
                return $this->reply;
            }
        };
    }

    public function testPassesKeyAndArguments(): void
    {
        $client = $this->client([1, 4, 30, -1]);
        $result = new RedisStorage($client)->consume(new SlidingWindow(), 'api:ip:1.2.3.4', 5, 60, 1, 1234.5678912);
        $this->assertTrue($result->accepted);
        $this->assertSame(4, $result->remaining);
        $this->assertSame(30, $result->resetAfter);
        $this->assertNull($result->retryAfter, '-1 is no Retry-After');
        $this->assertSame([['nette-api:rate-limit:{api:ip:1.2.3.4}'], ['5', '60', '1', '1234.567891']], array_slice($client->calls[0], 1));

        $client = $this->client([0, 0, 30, 12]);
        $result = new RedisStorage($client, 'own:')->consume(new TokenBucket(), 'k', 5, 60, 2, 1.0);
        $this->assertFalse($result->accepted);
        $this->assertSame(12, $result->retryAfter);
        $this->assertSame(0, new RedisStorage($this->client([0, 0, 0, 0]))->consume(new TokenBucket(), 'k', 5, 60, 1, 1.0)->retryAfter, '0 is a Retry-After, only -1 is none');
        $this->assertSame(['own:{k}'], $client->calls[0][1]);

        new RedisStorage($client)->consume(new FixedWindow(), 'k', 5, 60, 3, 1.0);
        $this->assertSame(['5', '60', '3', '1.000000'], $client->calls[1][2]);
        $this->assertNotSame($client->calls[0][0], $client->calls[1][0], 'each algorithm has its script');
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unexpectedReplies(): iterable
    {
        yield 'false' => [false];
        yield 'short' => [[1, 2, 3]];
        yield 'strings' => [['1', '2', '3', '4']];
        yield 'map' => [[1, 2, 3, 'x' => 4]];
        yield 'accepted' => [['1', 2, 3, 4]];
        yield 'remaining' => [[1, 2.0, 3, 4]];
        yield 'resetAfter' => [[1, 2, null, 4]];
        yield 'retryAfter' => [[1, 2, 3, '4']];
        yield 'long' => [[1, 2, 3, 4, 5]];
    }

    #[DataProvider('unexpectedReplies')]
    public function testUnexpectedReplyIsExplained(mixed $reply): void
    {
        $this->expectException(RateLimitStorageException::class);
        $this->expectExceptionMessage("Redis returned an unexpected result for the rate limit counter 'nette-api:rate-limit:{k}': " . var_export($reply, true) . '. Check that the client returns the replies of EVAL unchanged, for example an own RedisClientInterface or a proxy in front of Redis.');
        new RedisStorage($this->client($reply))->consume(new FixedWindow(), 'k', 5, 60, 1, 1.0);
    }

    public function testClientErrorBecomesRateLimitStorageException(): void
    {
        $error = new \RuntimeException('Connection refused');
        try {
            new RedisStorage($this->client(null, $error))->consume(new TokenBucket(), 'k', 5, 60, 1, 1.0);
            $this->fail('an exception expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame("The rate limit counter 'nette-api:rate-limit:{k}' cannot be updated in Redis: Connection refused", $e->getMessage());
            $this->assertSame($error, $e->getPrevious());
            $this->assertSame(0, $e->getCode());
        }
    }

    public function testBugOfAClientIsNotHidden(): void
    {
        $error = new \LogicException('a bug');
        $this->expectExceptionObject($error);
        new RedisStorage($this->client(null, $error))->consume(new TokenBucket(), 'k', 5, 60, 1, 1.0);
    }

    public function testOtherClientIsExplained(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RedisStorage got stdClass, but it needs a Redis client: \Redis or \RedisCluster from the redis extension, Predis\ClientInterface from predis/predis, or your own Tomaj\NetteApi\RateLimit\Redis\RedisClientInterface.');
        new RedisStorage(new \stdClass());
    }

    public function testAlgorithmWithoutScriptIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('RedisStorage cannot use the rate limit algorithm Tomaj\NetteApi\Test\RateLimit\Algorithm\OnlyInPhp, Redis needs the whole decision as one atomic Lua script. Implement Tomaj\NetteApi\RateLimit\Algorithm\RedisScriptAlgorithmInterface in the algorithm, or use another storage.');
        new RedisStorage($this->client([1, 0, 1, -1]))->consume(new OnlyInPhp(), 'k', 5, 60, 1, 1.0);
    }
}
