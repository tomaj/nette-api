<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\TraitFixtures\Items;

use Tomaj\NetteApi\Input\Body;

final readonly class LineItem
{
    public function __construct(#[Body] public string $sku)
    {
    }
}
