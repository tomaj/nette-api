<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Nette\Application\Request;
use Tomaj\NetteApi\Api;

/**
 * What a rate limiter knows about the request
 *
 * ip() is a method because the address is read lazily: only a limiter keyed by ip calls the IP detector,
 * so an own detector that fails (or a container without one) does not break limits keyed by something else,
 * and the presenter reads the address once per request. The identity is a plain property, the presenter
 * already has it from AuthorizationIdentityInterface::getIdentity() after the authorization.
 */
final class RateLimitContext
{
    /**
     * @param string|(\Closure(): ?string)|null $ip the client IP, or how to get it when a limiter needs it
     * @param string|null $identity the caller from AuthorizationIdentityInterface::getIdentity(), null before the authorization
     *                              or when the authorization does not provide one
     */
    public function __construct(
        public readonly Api $api,
        public readonly Request $request,
        public readonly RateLimitStage $stage,
        private string|\Closure|null $ip,
        public readonly ?string $identity = null,
    ) {
    }

    /**
     * The client IP from IpDetectorInterface, read on the first use; null when the container has no such service
     */
    public function ip(): ?string
    {
        if ($this->ip instanceof \Closure) {
            $this->ip = ($this->ip)();
        }

        return $this->ip;
    }
}
