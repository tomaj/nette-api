<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * Base of the endpoint attributes (#[Endpoint], #[Get], #[Post], #[Put], #[Patch], #[Delete]).
 * NetteApiExtension discovery turns each one on a handler class into an api endpoint.
 */
abstract readonly class Route
{
    public function __construct(
        public string $method,
        public string|int $version,
        public string $package,
        public ?string $action = null,
    ) {
    }
}
