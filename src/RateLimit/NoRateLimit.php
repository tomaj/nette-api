<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

final class NoRateLimit implements RateLimitInterface
{
    #[\Override]
    public function check(): ?RateLimitResponse
    {
        return null;
    }
}
