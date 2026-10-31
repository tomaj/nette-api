<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Nette\Application\IResponse;

/**
 * The outcome of one rate limit policy for one request
 */
final readonly class RateLimitResult
{
    /**
     * @param bool $accepted false answers 429 Too Many Requests
     * @param int $limit how many units the policy allows in its window
     * @param int $remaining how many units are left after this request
     * @param int $window the window of the policy in seconds, sent as w in RateLimit-Policy
     * @param int|null $resetAfter seconds until the quota is available again, sent as t in RateLimit; null when it is not known, t is then left out
     * @param int|null $retryAfter seconds the client should wait when the request is not accepted, sent as Retry-After
     * @param string $policy the name of the policy, sent in RateLimit-Policy and RateLimit
     * @param IResponse|null $errorResponse an own 429 response instead of the error handler's one
     */
    public function __construct(
        public bool $accepted,
        public int $limit,
        public int $remaining,
        public int $window,
        public ?int $resetAfter,
        public ?int $retryAfter,
        public string $policy,
        public ?IResponse $errorResponse = null,
    ) {
    }
}
