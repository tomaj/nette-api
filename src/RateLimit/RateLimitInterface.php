<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

interface RateLimitInterface
{
    public function check(): ?RateLimitResponse;
}
