<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

use Tomaj\NetteApi\Input\CasterInterface;

final readonly class DependencyCaster implements CasterInterface
{
    public function cast(mixed $value): string
    {
        return strtoupper(is_string($value) ? $value : '');
    }
}
