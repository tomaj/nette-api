<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

trait DependencyNestedTrait
{
    public function nested(): string
    {
        return 'nested';
    }
}
