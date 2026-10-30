<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Logger;

/**
 * Replaces the value of a masked header or query parameter before it is passed to ApiLoggerInterface
 */
interface LogValueMaskerInterface
{
    /**
     * @param string $value the original value; for a query parameter it is URL-decoded
     * @return string|null the value written to the log, or null to leave the header or parameter out
     */
    public function mask(string $value): ?string;
}
