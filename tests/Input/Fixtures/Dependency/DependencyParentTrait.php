<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

trait DependencyParentTrait
{
    public function parentLabel(): string
    {
        return 'parent';
    }
}
