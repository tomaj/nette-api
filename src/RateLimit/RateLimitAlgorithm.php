<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * The algorithms usable in neon (api.rateLimits.name.algorithm) and in #[RateLimit(algorithm: ...)]
 */
enum RateLimitAlgorithm: string
{
    /** counts requests in fixed windows (00:00-00:01, 00:01-00:02, ...), simple but allows 2x limit around the window edge */
    case FixedWindow = 'fixedWindow';

    /** weights the previous window by how much of it still overlaps, smooths the window edge */
    case SlidingWindow = 'slidingWindow';

    /** a bucket of limit tokens refilled evenly over the interval, allows short bursts up to limit */
    case TokenBucket = 'tokenBucket';
}
