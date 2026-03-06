<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Nette\Application\IResponse;

class RateLimitResponse
{
    public function __construct(
        private int $limit,
        private int $remaining,
        private ?int $retryAfter = null,
        private ?IResponse $errorResponse = null
    ) {
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getRemaining(): int
    {
        return $this->remaining;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function getErrorResponse(): ?IResponse
    {
        return $this->errorResponse;
    }
}
