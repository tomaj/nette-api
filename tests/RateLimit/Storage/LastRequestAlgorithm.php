<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

final class LastRequestAlgorithm implements RateLimitAlgorithmInterface
{
    /** @var list<list<mixed>> */
    public array $calls = [];

    public bool $keep = false;

    #[\Override]
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
    {
        $this->calls[] = [$state, $limit, $interval, $cost, $now];
        return new AlgorithmStep(new StorageResult(true, 0, 0, null), $this->keep ? null : 'state ' . $now, 1);
    }
}
