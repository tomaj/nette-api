<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResult;

final readonly class FixedRateLimit implements RateLimiterInterface
{
    public function __construct(public int $limit = 10)
    {
    }

    #[\Override]
    public function consume(RateLimitContext $context, int $cost = 1): array
    {
        return [new RateLimitResult(true, $this->limit, $this->limit, 60, 60, null, 'fixed')];
    }
}
