<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\TraitFixtures\Lib;

use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Test\Input\TraitFixtures\Items\LineItem;

trait OrderFields
{
    /**
     * @param list<LineItem> $items
     */
    public function __construct(#[Body] public readonly array $items)
    {
    }
}
