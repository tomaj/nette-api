<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;

/**
 * A bucket of limit tokens refilled evenly, limit tokens per interval. A request takes one token; a full bucket allows a burst of limit requests, then requests pass at the refill rate.
 *
 * In neon: rateLimits: {name: {algorithm: tokenBucket, limit: 60, interval: 1 minute, key: ip}}
 */
final class TokenBucketLimiter extends StorageRateLimiter
{
    #[\Override]
    protected function algorithm(): RateLimitAlgorithmInterface
    {
        return new TokenBucket();
    }
}
