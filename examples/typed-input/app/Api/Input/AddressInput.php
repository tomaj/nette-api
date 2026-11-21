<?php

declare(strict_types=1);

namespace App\Api\Input;

use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;

final readonly class AddressInput
{
    public function __construct(
        #[Body]
        #[Length(min: 1, max: 120)]
        public string $street,
        #[Body]
        #[Pattern('/^[0-9]{5}$/')]
        public string $postalCode,
    ) {
    }
}
