<?php

declare(strict_types=1);

namespace App\Api\Input;

use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Validation\Range;

final readonly class ItemInput
{
    public function __construct(
        #[Body]
        public string $sku,
        #[Body]
        #[Range(min: 1)]
        public int $quantity,
    ) {
    }
}
