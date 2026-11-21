<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Inherited\Base;

use Tomaj\NetteApi\Test\Input\Fixtures\Inherited\LeafLevelsCaster;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority as Level;

/**
 * Declares list<Level> with an import of this file; a subclass in another namespace inherits the constructor.
 */
readonly class LevelsInput
{
    /**
     * @param list<Level> $levels
     * @param list<Level> $casted
     */
    public function __construct(
        #[Query] public array $levels = [],
        #[Query] #[Cast(LeafLevelsCaster::class)] public array $casted = [],
    ) {
    }
}
