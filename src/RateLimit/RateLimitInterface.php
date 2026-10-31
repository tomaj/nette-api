<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * @deprecated since 4.0, removed in 5.0. Use RateLimiterInterface or a built-in limiter (FixedWindowLimiter,
 *             SlidingWindowLimiter, TokenBucketLimiter); an implementation of this interface still works through LegacyRateLimitAdapter.
 */
interface RateLimitInterface
{
    public function check(): ?RateLimitResponse;
}
