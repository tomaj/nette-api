<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Algorithm;

use Tomaj\NetteApi\RateLimit\Algorithm\RedisScriptAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

/**
 * The custom algorithm from the documentation: requests must be at least interval / limit seconds apart
 */
final readonly class MinimumGap implements RedisScriptAlgorithmInterface
{
    #[\Override]
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
    {
        $gap = $interval / $limit;
        if (is_float($state) && $now - $state < $gap) {
            $wait = (int) ceil($gap - ($now - $state));
            return new AlgorithmStep(new StorageResult(false, 0, $wait, $wait));
        }

        return new AlgorithmStep(new StorageResult(true, 0, (int) ceil($gap), null), $now, (int) ceil($gap) + 1);
    }

    #[\Override]
    public function redisScript(): string
    {
        return <<<'LUA'
            local limit, interval, now = tonumber(ARGV[1]), tonumber(ARGV[2]), tonumber(ARGV[4])
            local gap = interval / limit
            local last = tonumber(redis.call('GET', KEYS[1] .. ':gap') or '')
            if last and now - last < gap then
                local wait = math.ceil(gap - (now - last))
                return {0, 0, wait, wait}
            end
            redis.call('SET', KEYS[1] .. ':gap', string.format('%.6f', now), 'EX', math.ceil(gap) + 1)
            return {1, 0, math.ceil(gap), -1}
            LUA;
    }
}
