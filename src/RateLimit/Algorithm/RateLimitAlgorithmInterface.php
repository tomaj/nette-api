<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Algorithm;

use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;

/**
 * How a rate limit decides, independent of where its state is kept.
 *
 * Every storage that only loads and saves a value (InMemoryStorage, NetteCacheStorage, your own KeyValueStorage)
 * calls step() with the state the algorithm returned last time. RedisStorage needs the decision to be atomic,
 * so an algorithm usable with Redis also implements RedisScriptAlgorithmInterface and gives the same logic as Lua.
 *
 * Use your algorithm in neon with rateLimits: {name: {algorithm: App\MyAlgorithm, limit: 60, interval: 1 minute}}.
 */
interface RateLimitAlgorithmInterface
{
    /**
     * @param mixed $state what step() returned in AlgorithmStep::$state before, null when there is nothing or it expired;
     *                     check its shape, it may come from another algorithm after a config change
     * @param int<1, max> $limit
     * @param int<1, max> $interval
     * @param int<1, max> $cost
     * @param float $now UNIX timestamp with microseconds
     */
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep;
}
