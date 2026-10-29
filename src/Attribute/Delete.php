<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * A DELETE endpoint, e.g. #[Delete(1, 'users')] for DELETE /v1/users or #[Delete(1, 'users', 'detail')] for DELETE /v1/users/detail.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Delete extends Route
{
    public function __construct(string|int $version, string $package, ?string $action = null)
    {
        parent::__construct('DELETE', $version, $package, $action);
    }
}
