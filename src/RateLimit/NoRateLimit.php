<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * No rate limit, the default of an endpoint
 */
final class NoRateLimit implements RateLimiterInterface, RateLimitInterface
{
    /**
     * @infection-ignore-all the cost is not used
     */
    #[\Override]
    public function consume(RateLimitContext $context, int $cost = 1): array
    {
        return [];
    }

    /**
     * @deprecated part of the deprecated RateLimitInterface
     */
    #[\Override]
    public function check(): ?RateLimitResponse
    {
        return null;
    }
}
