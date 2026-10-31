<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * More limits on one endpoint, for example 100 per minute per IP before the authorization and 1000 per hour
 * per identity after it. All limits are counted; any rejected one answers 429.
 *
 * In neon: rateLimit: [perIp, perUser]
 */
final readonly class RateLimiterChain implements RateLimiterInterface
{
    /** @var list<RateLimiterInterface> */
    private array $limiters;

    public function __construct(RateLimiterInterface ...$limiters)
    {
        $this->limiters = array_values($limiters);
    }

    #[\Override]
    public function consume(RateLimitContext $context, int $cost = 1): array
    {
        $results = [];
        foreach ($this->limiters as $limiter) {
            array_push($results, ...$limiter->consume($context, $cost));
        }

        return $results;
    }

    /**
     * @return list<RateLimiterInterface>
     */
    public function getLimiters(): array
    {
        return $this->limiters;
    }
}
