<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

/**
 * @internal used by ApiPresenter, may change in 4.x
 */
interface CorsOriginPolicy
{
    /**
     * CORS response headers for a request with the given Origin header value
     *
     * @return array<string, string>
     */
    public function headers(?string $requestOrigin): array;
}
