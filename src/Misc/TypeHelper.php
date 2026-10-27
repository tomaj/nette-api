<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use Stringable;

/**
 * Small helpers for narrowing untyped input (request data, decoded JSON) to concrete types.
 *
 * @internal
 */
final class TypeHelper
{
    /**
     * Converts a value to a string for use in messages, URLs and headers.
     * Scalars and Stringable objects are converted directly, other values are JSON encoded.
     */
    public static function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if (is_int($value) || is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * Returns the value when it is an array, otherwise an empty array.
     *
     * @return array<array-key, mixed>
     */
    public static function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Returns a list of strings from the given value; non-array values give an empty list.
     *
     * @return list<string>
     */
    public static function toStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(self::toString(...), $value));
    }
}
