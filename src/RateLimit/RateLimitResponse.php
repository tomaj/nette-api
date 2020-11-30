<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Nette\Application\IResponse;

class RateLimitResponse
{
    private $limit;

    private $remaining;

    private $retryAfter;

    private $errorResponse;

    public function __construct(int $limit, int $remaining, ?int $retryAfter = null, ?IResponse $errorResponse = null)
    {
        $this->limit = $limit;
        $this->remaining = $remaining;
        $this->retryAfter = $retryAfter;
        $this->errorResponse = $errorResponse;
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
