<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Key;

use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\RateLimitContext;

final class OwnKey implements RateLimitKeyInterface
{
    #[\Override]
    public function key(RateLimitContext $context): string
    {
        return 'own';
    }

    #[\Override]
    public function needsIdentity(): bool
    {
        return false;
    }
}
