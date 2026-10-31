<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Algorithm;

use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

/**
 * A bucket of limit tokens that refills evenly over the interval. Allows short bursts up to the limit.
 */
final readonly class TokenBucket implements RedisScriptAlgorithmInterface
{
    /**
     * @param mixed $state [tokens, time of the last request]
     */
    #[\Override]
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
    {
        $rate = $limit / $interval;
        $tokens = $limit;
        $time = $now;
        if (is_array($state) && is_float($state[0] ?? null) && is_float($state[1] ?? null)) {
            $tokens = min($limit, $state[0] + max(0, $now - $state[1]) * $rate);
            // a server with a clock behind the others must not move the time back, the next request would refill twice;
            // a time further ahead is from a wrong clock, the bucket would not refill for the whole difference
            $time = $state[1] - $now <= $interval ? max($now, $state[1]) : $now;
        }
        $accepted = $tokens >= $cost;
        if ($accepted) {
            $tokens -= $cost;
        }
        // the bucket refills from its time, which is ahead of the clock of a server behind the others
        $behind = $time - $now;

        return new AlgorithmStep(
            new StorageResult(
                $accepted,
                (int) floor($tokens),
                (int) ceil($behind + ($limit - $tokens) / $rate),
                $accepted ? null : (int) ceil($behind + ($cost - $tokens) / $rate),
            ),
            [(float) $tokens, $time],
            (int) ceil($behind) + $interval + 1,
        );
    }

    #[\Override]
    public function redisScript(): string
    {
        return <<<'LUA'
            local limit, interval, cost, now = tonumber(ARGV[1]), tonumber(ARGV[2]), tonumber(ARGV[3]), tonumber(ARGV[4])
            local rate = limit / interval
            local state = redis.call('HMGET', KEYS[1], 'tokens', 'time')
            local tokens = limit
            local time = now
            if state[1] and state[2] then
                local stored = tonumber(state[2])
                tokens = math.min(limit, tonumber(state[1]) + math.max(0, now - stored) * rate)
                if stored - now <= interval then
                    time = math.max(now, stored)
                end
            end
            local behind = time - now
            local accepted = 0
            local retryAfter = -1
            if tokens >= cost then
                accepted = 1
                tokens = tokens - cost
            else
                retryAfter = math.ceil(behind + (cost - tokens) / rate)
            end
            redis.call('HSET', KEYS[1], 'tokens', string.format('%.17g', tokens), 'time', string.format('%.6f', time))
            redis.call('EXPIRE', KEYS[1], math.ceil(behind) + interval + 1)
            return {accepted, math.floor(tokens), math.ceil(behind + (limit - tokens) / rate), retryAfter}
            LUA;
    }
}
