<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

use Predis\ClientInterface;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Algorithm\RedisScriptAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Redis\PhpRedisClient;
use Tomaj\NetteApi\RateLimit\Redis\PredisClient;
use Tomaj\NetteApi\RateLimit\Redis\RedisClientInterface;

/**
 * Counters in Redis. Each check is one Lua script given by the algorithm (RedisScriptAlgorithmInterface), so the limit holds exactly even for parallel requests
 * and for many application servers sharing one Redis.
 *
 *     new RedisStorage(new PhpRedisClient($redis))      // ext-redis
 *     new RedisStorage(new PredisClient($predis))       // predis/predis
 *     new RedisStorage($redis)                          // \Redis, \RedisCluster or Predis\ClientInterface directly
 */
final readonly class RedisStorage implements RateLimitStorageInterface
{
    public const string PREFIX = 'nette-api:rate-limit:';

    private RedisClientInterface $client;

    /**
     * @param object $client \Redis or \RedisCluster (ext-redis), \Predis\ClientInterface (predis/predis) or own RedisClientInterface
     */
    public function __construct(object $client, private string $prefix = self::PREFIX)
    {
        $this->client = match (true) {
            $client instanceof RedisClientInterface => $client,
            $client instanceof \Redis, $client instanceof \RedisCluster => new PhpRedisClient($client),
            $client instanceof ClientInterface => new PredisClient($client),
            default => throw new \InvalidArgumentException(sprintf(
                'RedisStorage got %s, but it needs a Redis client: \Redis or \RedisCluster from the redis extension, Predis\ClientInterface from predis/predis, or your own %s.',
                $client::class,
                RedisClientInterface::class,
            )),
        };
    }

    #[\Override]
    public function consume(RateLimitAlgorithmInterface $algorithm, string $key, int $limit, int $interval, int $cost, float $now): StorageResult
    {
        if (!$algorithm instanceof RedisScriptAlgorithmInterface) {
            throw new \LogicException(sprintf(
                'RedisStorage cannot use the rate limit algorithm %s, Redis needs the whole decision as one atomic Lua script. Implement %s in the algorithm, or use another storage.',
                $algorithm::class,
                RedisScriptAlgorithmInterface::class,
            ));
        }

        return $this->run($algorithm->redisScript(), $key, [$limit, $interval, $cost], $now);
    }

    /**
     * @param list<int> $arguments
     */
    private function run(string $script, string $key, array $arguments, float $now): StorageResult
    {
        $arguments = [...array_map(strval(...), $arguments), sprintf('%.6F', $now)];
        // the hash tag keeps all counters of a key in one Redis Cluster slot
        $redisKey = $this->prefix . '{' . $key . '}';
        try {
            $result = $this->client->evaluate($script, [$redisKey], $arguments);
        } catch (\LogicException $e) {
            throw $e; // a bug of an own client, not an outage
        } catch (\Exception $e) {
            throw new RateLimitStorageException(sprintf("The rate limit counter '%s' cannot be updated in Redis: %s", $redisKey, $e->getMessage()), 0, $e);
        }
        [$accepted, $remaining, $resetAfter, $retryAfter] = is_array($result) && array_is_list($result) && count($result) === 4 ? $result : [null, null, null, null];
        if (!is_int($accepted) || !is_int($remaining) || !is_int($resetAfter) || !is_int($retryAfter)) {
            throw new RateLimitStorageException(sprintf("Redis returned an unexpected result for the rate limit counter '%s': %s. Check that the client returns the replies of EVAL unchanged, for example an own RedisClientInterface or a proxy in front of Redis.", $redisKey, var_export($result, true)));
        }

        return new StorageResult($accepted === 1, $remaining, $resetAfter, $retryAfter < 0 ? null : $retryAfter);
    }
}
