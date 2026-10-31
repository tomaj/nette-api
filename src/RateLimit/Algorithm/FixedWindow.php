<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Algorithm;

use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

/**
 * Counts requests in fixed windows of the interval (12:00:00-12:00:59, 12:01:00-12:01:59, ...).
 * Simple and cheap, but a client can send up to twice the limit around the edge of two windows.
 */
final readonly class FixedWindow implements RedisScriptAlgorithmInterface
{
    /**
     * @param mixed $state [window start, count]
     */
    #[\Override]
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
    {
        $start = intdiv((int) $now, $interval) * $interval;
        if (is_array($state) && is_int($state[0] ?? null)) {
            // a server with a clock behind the others must not reset the newer window of the others
            $start = max($start, intdiv($state[0], $interval) * $interval);
        }
        // only a state of two items is a count of this algorithm, [start, previous, current] of the sliding window is not
        $count = is_array($state) && count($state) === 2 && ($state[0] ?? null) === $start && is_int($state[1] ?? null) ? $state[1] : 0;
        // the times are measured by the clock of this server, so they are honest for it and the state lives long enough
        $resetAfter = (int) ceil($start + $interval - $now);
        if ($count + $cost > $limit) {
            return new AlgorithmStep(new StorageResult(false, max(0, $limit - $count), $resetAfter, $resetAfter));
        }
        $count += $cost;

        return new AlgorithmStep(new StorageResult(true, $limit - $count, $resetAfter, null), [$start, $count], $resetAfter + 1);
    }

    #[\Override]
    public function redisScript(): string
    {
        return <<<'LUA'
            local limit, window, cost, now = tonumber(ARGV[1]), tonumber(ARGV[2]), tonumber(ARGV[3]), tonumber(ARGV[4])
            -- the whole state is one hash, like step(): [start, count]
            local state = redis.call('HMGET', KEYS[1], 'start', 'count')
            local stored = tonumber(state[1])
            local start = math.floor(now / window) * window
            if stored then
                -- a server with a clock behind the others must not count in the older window, like step()
                start = math.max(start, math.floor(stored / window) * window)
            end
            local count = 0
            if stored == start and tonumber(state[2]) then
                count = tonumber(state[2])
            end
            local resetAfter = math.ceil(start + window - now)
            if count + cost > limit then
                return {0, math.max(0, limit - count), resetAfter, resetAfter}
            end
            count = count + cost
            -- the new state replaces the whole hash, also the fields of another algorithm, like the state of step()
            redis.call('DEL', KEYS[1])
            redis.call('HSET', KEYS[1], 'start', string.format('%d', start), 'count', string.format('%d', count))
            redis.call('EXPIRE', KEYS[1], resetAfter + 1)
            return {1, limit - count, resetAfter, -1}
            LUA;
    }
}
