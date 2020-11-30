<?php

declare(strict_types=1);

namespace RateLimit;

use Nette\Application\Responses\TextResponse;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;

class RateLimitResponseTest extends TestCase
{
    public function testGetters()
    {
        $rateLimitResponse = new RateLimitResponse(60, 50);
        $this->assertEquals(60, $rateLimitResponse->getLimit());
        $this->assertEquals(50, $rateLimitResponse->getRemaining());
        $this->assertNull($rateLimitResponse->getRetryAfter());
        $this->assertNull($rateLimitResponse->getErrorResponse());

        $rateLimitResponse = new RateLimitResponse(60, 0, 3600);
        $this->assertEquals(60, $rateLimitResponse->getLimit());
        $this->assertEquals(0, $rateLimitResponse->getRemaining());
        $this->assertEquals(3600, $rateLimitResponse->getRetryAfter());
        $this->assertNull($rateLimitResponse->getErrorResponse());

        $rateLimitResponse = new RateLimitResponse(60, 0, 3600, new TextResponse('My error response'));
        $this->assertEquals(60, $rateLimitResponse->getLimit());
        $this->assertEquals(0, $rateLimitResponse->getRemaining());
        $this->assertEquals(3600, $rateLimitResponse->getRetryAfter());
        $errorResponse = $rateLimitResponse->getErrorResponse();
        $this->assertInstanceOf(TextResponse::class, $errorResponse);
        $this->assertEquals('My error response', $errorResponse->getSource());
    }
}
