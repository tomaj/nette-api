<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\TraitFixtures\Lib;

use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Test\Input\TraitFixtures\Items\LineItem;

trait TagFields
{
    public function __construct(
        /** @var list<LineItem> */
        #[Body] public readonly array $tags,
    ) {
    }
}
