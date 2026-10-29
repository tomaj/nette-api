<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * Authorization of all endpoints of the handler class: a name from api.authorizations, '@service',
 * a class name, or false for a public endpoint. Without it, api.defaults.authorization applies.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Authorization
{
    public function __construct(
        public string|false $authorization,
    ) {
    }
}
