<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\RateLimit\LegacyRateLimitAdapter;
use Tomaj\NetteApi\RateLimit\NoRateLimit;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;

final readonly class ApiHolder
{
    private RateLimiterInterface $rateLimit;

    /**
     * Allows to define api with handler as a string.
     * Alows asynchronous loading of handlers if handler is defined as a string.
     * ApiDecider will try to load the handler from container.
     * Should not be used outside of ApiDecider.
     */
    public function __construct(
        private EndpointInterface $endpoint,
        private ApiHandlerInterface|string $handler,
        private ApiAuthorizationInterface $authorization,
        RateLimiterInterface|RateLimitInterface|null $rateLimit = null,
    ) {
        $this->rateLimit = $rateLimit === null ? new NoRateLimit() : LegacyRateLimitAdapter::wrap($rateLimit);
    }

    public function getEndpoint(): EndpointInterface
    {
        return $this->endpoint;
    }

    public function getHandler(): ApiHandlerInterface|string
    {
        return $this->handler;
    }

    public function getAuthorization(): ApiAuthorizationInterface
    {
        return $this->authorization;
    }

    /**
     * The rate limit of the endpoint; a RateLimitInterface of 3.x is wrapped in LegacyRateLimitAdapter
     */
    public function getRateLimit(): RateLimiterInterface
    {
        return $this->rateLimit;
    }
}
