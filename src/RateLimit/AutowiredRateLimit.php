<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * The default of ApiDecider::addApi(): the rate limit was left out, so the autowired RateLimitInterface service
 * is used like in 3.x. It is replaced before the endpoint is registered and never counts a request.
 *
 * @internal
 */
final readonly class AutowiredRateLimit implements RateLimiterInterface
{
    /**
     * The default of $cost is the one of the interface, it never counts, so there is nothing to mutate
     *
     * @infection-ignore-all
     */
    #[\Override]
    public function consume(RateLimitContext $context, int $cost = 1): array
    {
        throw new \LogicException('AutowiredRateLimit only marks a rate limit left out in ApiDecider::addApi(), it cannot count requests.');
    }
}
