<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;

final readonly class LegacyRateLimit implements RateLimitInterface
{
    public function __construct(public int $limit = 10)
    {
    }

    #[\Override]
    public function check(): ?RateLimitResponse
    {
        return new RateLimitResponse($this->limit, $this->limit);
    }
}
