<?php

namespace Tomaj\NetteApi\RateLimit;

use Nette\Application\Responses\TextResponse;

class NoRateLimit implements RateLimitInterface
{
    public function check(): ?RateLimitResponse
    {

        return new RateLimitResponse(40, 0, 123, new TextResponse('blablabla'));

        return null;
    }
}
