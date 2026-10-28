<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

/**
 * Expected type of an input value.
 *
 * The string constants are kept for backward compatibility, so both `InputType::Integer`
 * and `InputType::INTEGER` (string `'integer'`) are accepted wherever a value type is expected.
 */
enum InputType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Double = 'double';
    case Float = 'float';
    case String = 'string';
    case Array = 'array'; // Returns array of mixed values. If you want to validate array of specific type use setMulti() with expected values in array

    public const BOOLEAN = 'boolean';
    public const INTEGER = 'integer';
    public const DOUBLE = 'double';
    public const FLOAT = 'float';
    public const STRING = 'string';
    public const ARRAY = 'array';

    /**
     * Normalizes an enum case or a legacy string type to its string value.
     */
    public static function toValue(self|string|null $type): ?string
    {
        return $type instanceof self ? $type->value : $type;
    }
}
