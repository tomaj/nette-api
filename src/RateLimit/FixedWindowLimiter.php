<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;

/**
 * Counts requests in fixed windows of the interval (12:00:00-12:00:59, 12:01:00-12:01:59, ...). Simple and cheap, but a client can send up to twice the limit around the edge of two windows.
 *
 * In neon: rateLimits: {name: {algorithm: fixedWindow, limit: 60, interval: 1 minute, key: ip}}
 */
final class FixedWindowLimiter extends StorageRateLimiter
{
    #[\Override]
    protected function algorithm(): RateLimitAlgorithmInterface
    {
        return new FixedWindow();
    }
}
