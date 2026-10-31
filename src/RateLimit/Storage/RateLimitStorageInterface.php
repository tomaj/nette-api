<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;

/**
 * Keeps the state of the rate limits and runs an algorithm on it. Storages and algorithms combine freely:
 * KeyValueStorage (InMemoryStorage, NetteCacheStorage) loads the state, calls the algorithm and saves the result,
 * RedisStorage runs the Lua script of the algorithm atomically.
 *
 * Times are in seconds, $now is a UNIX timestamp with microseconds from the clock of the limiter.
 */
interface RateLimitStorageInterface
{
    /**
     * @param int<1, max> $limit
     * @param int<1, max> $interval
     * @param int<1, max> $cost
     * @throws RateLimitStorageException when the storage is not available; the built-in limiters then let the request through,
     *                                   like for any other exception except a \LogicException
     */
    public function consume(RateLimitAlgorithmInterface $algorithm, string $key, int $limit, int $interval, int $cost, float $now): StorageResult;
}
