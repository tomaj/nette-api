<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * Rate limit of all endpoints of the handler class: a name from api.rateLimits, '@service',
 * a class name, or false for none. Without it, api.defaults.rateLimit applies.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class RateLimit
{
    public function __construct(
        public string|false $rateLimit,
    ) {
    }
}
