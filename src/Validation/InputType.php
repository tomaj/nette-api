<?php

namespace Tomaj\NetteApi\Validation;

enum InputType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Double = 'double';
    case Float = 'float';
    case String = 'string';
    case Array = 'array';
}
