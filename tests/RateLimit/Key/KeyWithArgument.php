<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Key;

use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\RateLimitContext;

final readonly class KeyWithArgument implements RateLimitKeyInterface
{
    public function __construct(private string $prefix)
    {
    }

    #[\Override]
    public function key(RateLimitContext $context): string
    {
        return $this->prefix;
    }

    #[\Override]
    public function needsIdentity(): bool
    {
        return false;
    }
}
