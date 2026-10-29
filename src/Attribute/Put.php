<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * A PUT endpoint, e.g. #[Put(1, 'users')] for PUT /v1/users or #[Put(1, 'users', 'detail')] for PUT /v1/users/detail.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Put extends Route
{
    public function __construct(string|int $version, string $package, ?string $action = null)
    {
        parent::__construct('PUT', $version, $package, $action);
    }
}
