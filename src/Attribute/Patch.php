<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * A PATCH endpoint, e.g. #[Patch(1, 'users')] for PATCH /v1/users or #[Patch(1, 'users', 'detail')] for PATCH /v1/users/detail.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Patch extends Route
{
    public function __construct(string|int $version, string $package, ?string $action = null)
    {
        parent::__construct('PATCH', $version, $package, $action);
    }
}
