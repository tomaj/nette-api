<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Tomaj\NetteApi\RateLimit\Algorithm\SlidingWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;

/**
 * Counts the current window plus the part of the previous window that still overlaps the last interval, so the edge of two windows does not allow a double burst. The estimate assumes the previous window was evenly spread.
 *
 * In neon: rateLimits: {name: {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}}
 */
final class SlidingWindowLimiter extends StorageRateLimiter
{
    #[\Override]
    protected function algorithm(): RateLimitAlgorithmInterface
    {
        return new SlidingWindow();
    }
}
