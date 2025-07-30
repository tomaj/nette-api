<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\RateLimit\NoRateLimit;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;

readonly class Api
{
    public readonly RateLimitInterface $rateLimit {
        get => $this->rateLimit ?? new NoRateLimit();
    }

    public function __construct(
        public readonly EndpointInterface $endpoint,
        public readonly ApiHandlerInterface|string $handler,
        public readonly ApiAuthorizationInterface $authorization,
        ?RateLimitInterface $rateLimit = null
    ) {
        $this->rateLimit = $rateLimit;
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

    public function getRateLimit(): RateLimitInterface
    {
        return $this->rateLimit;
    }
}
