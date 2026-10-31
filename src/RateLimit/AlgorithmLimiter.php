<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Psr\Clock\ClockInterface;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tracy\ILogger;

/**
 * A limiter with any algorithm, for example your own one. In neon: rateLimits: {name: {algorithm: App\MyAlgorithm, ...}}
 */
final class AlgorithmLimiter extends StorageRateLimiter
{
    /**
     * @param int|string $interval seconds or an interval like '1 minute'
     */
    public function __construct(
        private readonly RateLimitAlgorithmInterface $algorithm,
        string $policy,
        int $limit,
        int|string $interval,
        RateLimitKeyInterface $key,
        RateLimitStorageInterface $storage,
        ?ClockInterface $clock = null,
        ?ILogger $logger = null,
    ) {
        parent::__construct($policy, $limit, $interval, $key, $storage, $clock, $logger);
    }

    #[\Override]
    protected function algorithm(): RateLimitAlgorithmInterface
    {
        return $this->algorithm;
    }
}
