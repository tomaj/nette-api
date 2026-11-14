<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Validation\Length;

// the input of GreetHandler: ?name=..., optional, 1 to 40 characters
final readonly class GreetInput
{
    public function __construct(
        #[Query(description: 'Who to greet')]
        #[Length(min: 1, max: 40)]
        public string $name = 'world',
    ) {
    }
}
