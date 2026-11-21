<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

trait DependencyTrait
{
    use DependencyNestedTrait;

    public const string SUFFIX = '!';

    public function label(): string
    {
        return DependencyUnused::name() . self::SUFFIX;
    }
}
