<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Converts one raw request value into the property's PHP value.
 * Report invalid client input with CastException; any other exception is a programmer error.
 */
interface CasterInterface
{
    public function cast(mixed $value): mixed;
}
