<?php

declare(strict_types=1);

namespace App;

use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;

/**
 * No requests left, retry in 30 seconds. Without an own error response ApiPresenter asks
 * the error handler for the 429, see examples/rate-limit for a real limit.
 */
final class ExhaustedRateLimit implements RateLimitInterface
{
    public function check(): ?RateLimitResponse
    {
        return new RateLimitResponse(limit: 10, remaining: 0, retryAfter: 30);
    }
}
