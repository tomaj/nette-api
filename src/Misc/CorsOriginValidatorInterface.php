<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

/**
 * Decides at request time which origins may call the API from a browser,
 * for origins that are not known in advance (for example customer domains stored in a database).
 *
 * Used by ApiPresenter::setCorsOriginValidator() and api: cors: originValidator in neon.
 */
interface CorsOriginValidatorInterface
{
    /**
     * @param string $origin the request Origin header, always scheme://host[:port] (never 'null', a path or a list)
     * @return bool true reflects the origin in Access-Control-Allow-Origin
     */
    public function isAllowedOrigin(string $origin): bool;
}
