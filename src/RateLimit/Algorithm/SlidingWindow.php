<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Algorithm;

use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

/**
 * Weights the count of the previous window by how much of it still overlaps the last interval,
 * which smooths the edge of the windows. The default algorithm.
 */
final readonly class SlidingWindow implements RedisScriptAlgorithmInterface
{
    /**
     * @param mixed $state [current window start, previous window count, current window count]
     */
    #[\Override]
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
    {
        $start = intdiv((int) $now, $interval) * $interval;
        if (is_array($state) && is_int($state[0] ?? null)) {
            // a server with a clock behind the others must not reset the newer window of the others
            $start = max($start, intdiv($state[0], $interval) * $interval);
        }
        [$previous, $current] = [0, 0];
        if (is_array($state) && is_int($state[1] ?? null) && is_int($state[2] ?? null)) {
            [$previous, $current] = match ($state[0] ?? null) {
                $start => [$state[1], $state[2]],
                $start - $interval => [$state[2], 0],
                default => [0, 0],
            };
        }
        // the weight of the previous window by the newest clock, the times by the clock of this server
        $weight = 1 - (max($now, $start) - $start) / $interval;
        $estimate = $previous * $weight + $current;
        $untilNext = $start + $interval - $now;
        if ($estimate + $cost > $limit) {
            // while the current window has room, wait until enough of the previous window slides out;
            // when the current window alone is full, it becomes the previous window and must slide out enough
            // a positive limit - current - cost means previous >= 2 here, so max(1, ...) and max(2, ...) are the same
            /** @infection-ignore-all */
            $retryAfter = (int) ceil(
                $untilNext
                - $interval * max(0, $limit - $current - $cost) / max(1, $previous)
                + $interval * max(0, $current + $cost - $limit) / max(1, $current),
            );
            return new AlgorithmStep(new StorageResult(false, max(0, (int) floor($limit - $estimate)), (int) ceil($untilNext), $retryAfter));
        }

        return new AlgorithmStep(
            new StorageResult(true, (int) floor($limit - $estimate - $cost), (int) ceil($untilNext), null),
            [$start, $previous, $current + $cost],
            (int) ceil($untilNext) + $interval + 1,
        );
    }

    #[\Override]
    public function redisScript(): string
    {
        return <<<'LUA'
            local limit, window, cost, now = tonumber(ARGV[1]), tonumber(ARGV[2]), tonumber(ARGV[3]), tonumber(ARGV[4])
            local start = math.floor(now / window) * window
            -- a server with a clock behind the others must not count in the older windows, like step()
            local latest = tonumber(redis.call('GET', KEYS[1] .. ':start') or '0')
            start = math.max(start, math.floor(latest / window) * window)
            local currentKey = KEYS[1] .. ':' .. string.format('%d', start)
            local previous = tonumber(redis.call('GET', KEYS[1] .. ':' .. string.format('%d', start - window)) or '0')
            local current = tonumber(redis.call('GET', currentKey) or '0')
            local weight = 1 - (math.max(now, start) - start) / window
            local estimate = previous * weight + current
            local untilNext = start + window - now
            local retryAfter = -1
            local accepted = 0
            if estimate + cost <= limit then
                accepted = 1
                current = redis.call('INCRBY', currentKey, cost)
                estimate = estimate + cost
                redis.call('EXPIRE', currentKey, math.ceil(untilNext) + window + 1)
                redis.call('SET', KEYS[1] .. ':start', string.format('%d', start), 'EX', math.ceil(untilNext) + window + 1)
            elseif current + cost <= limit then
                retryAfter = math.ceil(untilNext - window * (limit - current - cost) / previous)
            else
                retryAfter = math.ceil(untilNext + window * (current + cost - limit) / current)
            end
            return {accepted, math.max(0, math.floor(limit - estimate)), math.ceil(untilNext), retryAfter}
            LUA;
    }
}
