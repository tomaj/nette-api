<?php

declare(strict_types=1);

namespace App;

use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;

/**
 * A fixed window per client IP, counted in a file. Use Redis or a database in production.
 */
final class FileRateLimit implements RateLimitInterface
{
    public function __construct(
        private IpDetectorInterface $ipDetector,
        private string $directory,
        private int $limit = 3,
        private int $window = 60,
    ) {
    }

    public function check(): ?RateLimitResponse
    {
        $windowStart = intdiv(time(), $this->window) * $this->window;
        $file = $this->directory . '/rate-' . md5($this->ipDetector->getRequestIp()) . '-' . $windowStart;

        $hits = (int) @file_get_contents($file) + 1;
        file_put_contents($file, (string) $hits, LOCK_EX);

        $retryAfter = $windowStart + $this->window - time();

        // ApiPresenter answers 429 with Retry-After when remaining is 0,
        // so the request being checked is not counted in remaining yet
        $remaining = max(0, $this->limit - $hits + 1);
        return new RateLimitResponse($this->limit, $remaining, $retryAfter);
    }
}
