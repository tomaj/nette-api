<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * A POST endpoint, e.g. #[Post(1, 'users')] for POST /v1/users or #[Post(1, 'users', 'detail')] for POST /v1/users/detail.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Post extends Route
{
    public function __construct(string|int $version, string $package, ?string $action = null)
    {
        parent::__construct('POST', $version, $package, $action);
    }
}
