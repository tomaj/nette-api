<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

final class InvokableOrigins
{
    public function __invoke(string $origin): bool
    {
        return true;
    }
}
