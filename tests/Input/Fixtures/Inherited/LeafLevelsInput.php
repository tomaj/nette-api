<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Inherited;

use Tomaj\NetteApi\Test\Input\Fixtures\Inherited\Base\LevelsInput;

/**
 * Level is not imported here, so list<Level> only resolves in the file of the parent.
 */
final readonly class LeafLevelsInput extends LevelsInput
{
}
