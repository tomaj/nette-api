<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\TraitFixtures\Lib;

use Tomaj\NetteApi\Test\Input\TraitFixtures\Items\LineItem;

trait CastsItems
{
    /**
     * @return list<LineItem>
     */
    public function cast(mixed $value): array
    {
        return array_map(static fn (string $sku): LineItem => new LineItem($sku), explode(',', is_string($value) ? $value : ''));
    }
}
