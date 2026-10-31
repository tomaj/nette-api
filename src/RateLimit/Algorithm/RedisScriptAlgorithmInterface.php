<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Algorithm;

/**
 * An algorithm that RedisStorage can run atomically, as one Lua script
 */
interface RedisScriptAlgorithmInterface extends RateLimitAlgorithmInterface
{
    /**
     * The Lua script with the same logic as step().
     *
     * KEYS[1] is the key of the counter; write own keys as KEYS[1] .. ':name' (they stay in the same Redis Cluster
     * slot and do not meet the keys of the built-in algorithms, the token bucket uses KEYS[1] itself,
     * the windows KEYS[1] .. ':start' and KEYS[1] .. ':<window start>').
     * ARGV are limit, interval, cost and now (a UNIX timestamp with microseconds).
     * It returns {accepted (1 or 0), remaining, resetAfter, retryAfter (-1 when accepted)} as integers
     * and sets an expiration on every key it writes.
     */
    public function redisScript(): string;
}
