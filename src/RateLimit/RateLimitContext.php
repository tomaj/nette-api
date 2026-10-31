<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Nette\Application\Request;
use Tomaj\NetteApi\Api;

/**
 * What a rate limiter knows about the request
 */
final readonly class RateLimitContext
{
    /**
     * @param string|null $identity the caller from AuthorizationIdentityInterface::getIdentity(), null before the authorization
     *                              or when the authorization does not provide one
     * @param string|null $ip the client IP from IpDetectorInterface, null when the container has no such service
     */
    public function __construct(
        public Api $api,
        public Request $request,
        public RateLimitStage $stage,
        public ?string $ip,
        public ?string $identity = null,
    ) {
    }
}
