<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Algorithm;

use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

/**
 * An algorithm without a Lua script, it cannot run in Redis
 */
final readonly class OnlyInPhp implements RateLimitAlgorithmInterface
{
    #[\Override]
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
    {
        return new AlgorithmStep(new StorageResult(true, $limit - $cost, $interval, null));
    }
}
