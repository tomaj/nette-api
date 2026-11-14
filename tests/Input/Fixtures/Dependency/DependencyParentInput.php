<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;

abstract readonly class DependencyParentInput
{
    use DependencyParentTrait;

    /** @param list<DependencyItemEnum> $levels */
    public function __construct(
        #[Body] #[Cast(DependencyCaster::class)] public string $name,
        #[Body] public DependencyEnum $kind,
        #[Body] public array $levels,
        #[Body] #[DependencyConstraint] public string $note = '',
    ) {
    }
}
