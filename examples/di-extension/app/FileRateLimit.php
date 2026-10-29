<?php

declare(strict_types=1);

namespace App;

use Nette\Http\IRequest;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;

/**
 * Fixed window: at most $limit requests per client in $window seconds.
 * Counts in files to stay runnable without Redis; use Redis or APCu in production.
 */
final class FileRateLimit implements RateLimitInterface
{
    public function __construct(
        private IRequest $request,
        private string $storageDir,
        private int $limit = 2,
        private int $window = 60,
    ) {
    }

    public function check(): ?RateLimitResponse
    {
        // runs before authorization, so the key cannot be trusted yet; it only picks the bucket
        $client = $this->request->getHeader('X-Api-Key') ?? $this->request->getRemoteAddress() ?? 'unknown';
        $windowStart = intdiv(time(), $this->window) * $this->window;
        $file = $this->storageDir . '/rate-' . sha1($client) . '-' . $windowStart;

        $handle = fopen($file, 'c+');
        flock($handle, LOCK_EX);
        $usedBefore = (int) stream_get_contents($handle);
        if ($usedBefore < $this->limit) {
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) ($usedBefore + 1));
        }
        flock($handle, LOCK_UN);
        fclose($handle);

        $retryAfter = $windowStart + $this->window - time();

        // ApiPresenter answers 429 when remaining is 0, so remaining counts this request too:
        // with limit 2 the first two requests get 2 and 1, the third one gets 0 and a 429 problem
        return new RateLimitResponse(
            $this->limit,
            $this->limit - min($usedBefore, $this->limit),
            $retryAfter,
        );
    }
}
