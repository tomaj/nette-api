<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * More limits on one endpoint, for example 100 per minute per IP before the authorization and 1000 per hour
 * per identity after it. The limits are checked in order; the first rejected one answers 429 and the next ones
 * are not counted, so rejected requests of one client do not use up a quota shared with others. Put the limits
 * of one client before the shared ones.
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
        $rejected = false;
        foreach ($this->limiters as $limiter) {
            foreach ($limiter->consume($context, $cost) as $result) {
                $results[] = $result;
                $rejected = $rejected || !$result->accepted;
            }
            if ($rejected) {
                break;
            }
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
