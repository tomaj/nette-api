<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\TypedBad;

use Tomaj\NetteApi\Input\Query;

final readonly class BadInput
{
    public function __construct(
        #[Query]
        public mixed $value,
    ) {
    }
}
