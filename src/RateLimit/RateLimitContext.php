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
     * @param string|null $ip the client IP: from an own IP detector, or from Nette\Http\IRequest::getRemoteAddress() with the default IpDetector,
     *                        which trusts headers that any client can send
     * @param bool $hasIpDetector false when the container has no IpDetectorInterface service, so $ip is not known
     */
    public function __construct(
        public Api $api,
        public Request $request,
        public RateLimitStage $stage,
        public ?string $ip,
        public ?string $identity = null,
        public bool $hasIpDetector = true,
    ) {
    }
}
