<?php

declare(strict_types=1);

namespace App\Api\Input;

use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Validation\Range;

final readonly class ListUsersInput
{
    public function __construct(
        #[Query(description: 'Page number')]
        #[Range(min: 1)]
        public int $page = 1,
        #[Query]
        public ?UserStatus $status = null,
    ) {
    }
}
