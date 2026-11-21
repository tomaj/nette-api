<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\TraitFixtures\Casters;

use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Test\Input\TraitFixtures\Lib\CastsItems;

final class ItemsCaster implements CasterInterface
{
    use CastsItems;
}
