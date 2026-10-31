<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tracy\ILogger;

final class NullLogger implements ILogger
{
    #[\Override]
    public function log(mixed $value, string $level = self::INFO): void
    {
    }
}
