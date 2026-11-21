<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Inherited\Base;

use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority as Level;

/**
 * Documents its result with an import of this file; a subclass in another namespace inherits cast().
 */
class LevelsCaster implements CasterInterface
{
    /**
     * @return list<Level>
     */
    #[\Override]
    public function cast(mixed $value): array
    {
        return array_map(static fn (string $item): Level => Level::from((int) $item), explode(',', is_string($value) ? $value : ''));
    }
}
