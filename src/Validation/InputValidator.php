<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

class InputValidator
{
    /**
     * Summary of validate
     * @param mixed $value
     * @param ?string $expectedType
     */
    public function validate($value, $expectedType = null): ValidationResultInterface
    {
        if ($value === null || $expectedType === null) {
            return new ValidationResult(ValidationResult::STATUS_OK);
        }

        if (is_array($value) && $expectedType !== InputType::ARRAY) {
            return $this->validateMulti($value, $expectedType);
        }

        switch ($expectedType) {
            case InputType::BOOLEAN:
                if (!is_bool($value)) {
                    return new ValidationResult(ValidationResult::STATUS_ERROR, ['Value ' . $value .  ' has invalid type. Expected boolean.']);
                }
                break;
            case InputType::INTEGER:
                if (!is_int($value)) {
                    return new ValidationResult(ValidationResult::STATUS_ERROR, ['Value ' . $value .  ' has invalid type. Expected integer.']);
                }
                break;
            case InputType::DOUBLE:
                if (!is_float($value) && !is_int($value)) {
                    return new ValidationResult(ValidationResult::STATUS_ERROR, ['Value ' . $value .  ' has invalid type. Expected double.']);
                }
                break;
            case InputType::FLOAT:
                if (!is_float($value) && !is_int($value)) {
                    return new ValidationResult(ValidationResult::STATUS_ERROR, ['Value ' . $value .  ' has invalid type. Expected float.']);
                }
                break;
            case InputType::STRING:
                if (!is_string($value)) {
                    return new ValidationResult(ValidationResult::STATUS_ERROR, ['Value ' . $value .  ' has invalid type. Expected string.']);
                }
                break;
            case InputType::ARRAY:
                if (!is_array($value)) {
                    return new ValidationResult(ValidationResult::STATUS_ERROR, ['Value ' . $value .  ' has invalid type. Expected array.']);
                }
                break;
            default:
                return new ValidationResult(ValidationResult::STATUS_ERROR, ['Value ' . $value .  ' has invalid type.']);
        }
        return new ValidationResult(ValidationResult::STATUS_OK);
    }

    /**
     * Summary of transformType
     * @param mixed $value
     * @param ?string $expectedType
     */
    public function transformType($value, $expectedType = null): mixed
    {
        if ($value === null || $expectedType === null) {
            return $value;
        }

        if (is_array($value) && $expectedType !== InputType::ARRAY) {
            return $this->transformMulti($value, $expectedType);
        }

        switch ($expectedType) {
            case InputType::BOOLEAN:
                if ($value === '1' || $value === 1 || $value === true || strtolower((string) $value) === 'true') {
                    return true;
                } else {
                    return false;
                }
                // no break
            case InputType::INTEGER:
                if (is_numeric($value) || $value === '') {
                    settype($value, 'integer');
                }
                break;
            case InputType::DOUBLE:
                if (is_numeric($value) || $value === '') {
                    settype($value, 'double');
                }
                break;
            case InputType::FLOAT:
                if (is_numeric($value) || $value === '') {
                    settype($value, 'float');
                }
                break;
            case InputType::STRING:
                if (is_string($value)) {
                    settype($value, 'string');
                }
                break;
            default:
                return $value;
        }
        return $value;
    }

    /**
     * @param mixed[] $value
     */
    private function validateMulti(array $value, string $expectedType): ValidationResultInterface
    {
        foreach ($value as $item) {
            $result = $this->validate($item, $expectedType);
            if ($result->isOk() === false) {
                return $result;
            }
        }
        return new ValidationResult(ValidationResult::STATUS_OK);
    }

    /**
     * @param mixed[] $value
     * @return mixed[]
     */
    private function transformMulti(array $value, string $expectedType): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = $this->transformType($item, $expectedType);
        }
        return $value;
    }
}
