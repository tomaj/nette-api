<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

class InputValidator
{
    public function validate($value, ?InputType $expectedType = null): ValidationResultInterface
    {
        if ($value === null || $expectedType === null) {
            return new ValidationResult(ValidationResult::STATUS_OK);
        }
        $isError = false;
        match ($expectedType) {
            InputType::Boolean => is_bool($value) ? $isError = false : $isError = true,
            InputType::Integer => is_int($value) ? $isError = false : $isError = true,
            InputType::Double => is_float($value) || is_int($value) ? $isError = false : $isError = true,
            InputType::Float => is_float($value) || is_int($value) ? $isError = false : $isError = true,
            InputType::String => is_string($value) ? $isError = false : $isError = true,
            InputType::Array => is_array($value) ? $isError = false : $isError = true,
            default => false,
        };

        if ($isError) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, ['Value ' . $value .  ' has invalid type. Expected ' . $expectedType->value . '.']);
        }
        return new ValidationResult(ValidationResult::STATUS_OK);
    }

    public function transformType($value, ?InputType $expectedType = null)
    {
        if ($value === null || $expectedType === null) {
            return $value;
        }

        match ($expectedType) {
            InputType::Boolean => ($value === '1' || $value === 1 || $value === true || strtolower((string) $value) === 'true') ? true : false,
            InputType::Integer => is_numeric($value) ? settype($value, 'integer') : null,
            InputType::Double => is_numeric($value) ? settype($value, 'double') : null,
            InputType::Float => is_numeric($value) ? settype($value, 'float') : null,
            InputType::String => is_string($value) ? settype($value, 'string') : null,
            default => null,
        };
        return $value;
    }
}
