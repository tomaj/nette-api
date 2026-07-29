<?php

namespace Tomaj\NetteApi\Validation;

class InputType
{
    public const BOOLEAN = 'boolean';
    public const INTEGER = 'integer';
    public const DOUBLE = 'double';
    public const FLOAT = 'float';
    public const STRING = 'string';
    public const ARRAY = 'array'; // Returns array of mixed values. If you want to validate array of specific type use setMulti() with expected values in array
}
