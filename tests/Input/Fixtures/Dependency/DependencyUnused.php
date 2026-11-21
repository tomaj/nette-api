<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

/**
 * Only a static method of it is called, which does not change the metadata.
 */
final class DependencyUnused
{
    public static function name(): string
    {
        return 'unused';
    }
}
