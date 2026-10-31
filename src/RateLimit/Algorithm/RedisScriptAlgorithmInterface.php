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
     * KEYS[1] is the key of the counter, a hash; keep the whole state in its fields and do not build other keys,
     * a script may touch only the keys it declares (Redis Cluster and Dragonfly reject the others).
     * The built-in algorithms keep their state there too, use other fields than theirs: tokens and time of the token bucket,
     * start and count of the fixed window, start, prev and curr of the sliding window.
     * ARGV are limit, interval, cost and now (a UNIX timestamp with microseconds).
     * It returns {accepted (1 or 0), remaining, resetAfter, retryAfter (-1 when accepted)} as integers
     * and sets an expiration on KEYS[1] whenever it writes.
     */
    public function redisScript(): string;
}
