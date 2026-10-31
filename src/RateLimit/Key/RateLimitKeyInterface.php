<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Key;

use Tomaj\NetteApi\RateLimit\RateLimitContext;

/**
 * Decides who shares a quota: requests with the same key are counted together
 */
interface RateLimitKeyInterface
{
    /**
     * The key of the request, or null when the request cannot be keyed (for example no identity); the limit is then skipped
     */
    public function key(RateLimitContext $context): ?string;

    /**
     * True when the key needs the identity, so the limit is checked after the authorization; false checks it before
     */
    public function needsIdentity(): bool;
}
