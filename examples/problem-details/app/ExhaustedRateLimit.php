<?php

declare(strict_types=1);

namespace App;

use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResult;
use Tomaj\NetteApi\RateLimit\RateLimitStage;

/**
 * No requests left, retry in 30 seconds. Without an own error response ApiPresenter asks
 * the error handler for the 429, see examples/rate-limit for a real limit.
 */
final class ExhaustedRateLimit implements RateLimiterInterface
{
    public function consume(RateLimitContext $context, int $cost = 1): array
    {
        if ($context->stage !== RateLimitStage::BeforeAuthorization) {
            return [];
        }
        return [new RateLimitResult(accepted: false, limit: 10, remaining: 0, window: 60, resetAfter: 30, retryAfter: 30, policy: 'exhausted')];
    }
}
