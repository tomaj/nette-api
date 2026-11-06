<?php

declare(strict_types=1);

namespace App\Api\Input;

use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Length;

final readonly class CreateOrderInput
{
    /** @param list<ItemInput> $items */
    public function __construct(
        #[Body]
        #[Email]
        public string $customerEmail,
        #[Body]
        public AddressInput $address,
        #[Body]
        #[Length(min: 1, max: 50)]
        public array $items,
    ) {
    }
}
