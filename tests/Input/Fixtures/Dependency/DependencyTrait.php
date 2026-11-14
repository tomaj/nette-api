<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

trait DependencyTrait
{
    use DependencyNestedTrait;

    public function label(): string
    {
        return 'label';
    }
}
