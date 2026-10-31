<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

final readonly class StorageResult
{
    /**
     * @param int $remaining units left after the request
     * @param int $resetAfter seconds until the quota is available again
     * @param int|null $retryAfter seconds to wait when the request is not accepted, null when it is
     */
    public function __construct(
        public bool $accepted,
        public int $remaining,
        public int $resetAfter,
        public ?int $retryAfter,
    ) {
    }
}
