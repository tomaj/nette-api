<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * An endpoint with any HTTP method, e.g. #[Endpoint('OPTIONS', 1, 'users')].
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Endpoint extends Route
{
}
