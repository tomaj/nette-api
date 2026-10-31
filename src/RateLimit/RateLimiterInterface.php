<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * A rate limit of an endpoint, called by ApiPresenter twice per request: before the authorization
 * (RateLimitStage::BeforeAuthorization, no identity yet) and after a successful authorization
 * (RateLimitStage::AfterAuthorization, with the identity of the caller).
 *
 * Built-in implementations: FixedWindowLimiter, SlidingWindowLimiter, TokenBucketLimiter, RateLimiterChain.
 */
interface RateLimiterInterface
{
    /**
     * Counts the request and returns one result per policy that applies in the stage of the context.
     * An empty list means that no limit applies in this stage. A result that is not accepted answers 429.
     *
     * @param int $cost how many units the request takes, 1 for one request
     * @return list<RateLimitResult>
     */
    public function consume(RateLimitContext $context, int $cost = 1): array;
}
