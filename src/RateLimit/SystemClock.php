<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Psr\Clock\ClockInterface;

/**
 * The current time, the default clock of the rate limiters
 */
final readonly class SystemClock implements ClockInterface
{
    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
