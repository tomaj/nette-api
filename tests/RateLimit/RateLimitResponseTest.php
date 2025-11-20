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
        self::assertEquals(60, $rateLimitResponse->getLimit());
        self::assertEquals(50, $rateLimitResponse->getRemaining());
        self::assertNull($rateLimitResponse->getRetryAfter());
        self::assertNull($rateLimitResponse->getErrorResponse());

        $rateLimitResponse = new RateLimitResponse(60, 0, 3600);
        self::assertEquals(60, $rateLimitResponse->getLimit());
        self::assertEquals(0, $rateLimitResponse->getRemaining());
        self::assertEquals(3600, $rateLimitResponse->getRetryAfter());
        self::assertNull($rateLimitResponse->getErrorResponse());

        $rateLimitResponse = new RateLimitResponse(60, 0, 3600, new TextResponse('My error response'));
        self::assertEquals(60, $rateLimitResponse->getLimit());
        self::assertEquals(0, $rateLimitResponse->getRemaining());
        self::assertEquals(3600, $rateLimitResponse->getRetryAfter());
        $errorResponse = $rateLimitResponse->getErrorResponse();
        self::assertInstanceOf(TextResponse::class, $errorResponse);
        self::assertEquals('My error response', $errorResponse->getSource());
    }
}
