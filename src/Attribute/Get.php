<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * A GET endpoint, e.g. #[Get(1, 'users')] for GET /v1/users or #[Get(1, 'users', 'detail')] for GET /v1/users/detail.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Get extends Route
{
    public function __construct(string|int $version, string $package, ?string $action = null)
    {
        parent::__construct('GET', $version, $package, $action);
    }
}
