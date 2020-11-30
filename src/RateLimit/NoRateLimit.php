<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

class NoRateLimit implements RateLimitInterface
{
    public function check(): ?RateLimitResponse
    {
        return null;
    }
}
