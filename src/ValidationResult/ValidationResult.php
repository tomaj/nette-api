<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\ValidationResult;

use InvalidArgumentException;

class ValidationResult implements ValidationResultInterface
{
    public const STATUS_OK = 'OK';

    public const STATUS_ERROR = 'error';

    /**
     * @param array<mixed> $errors
     */
    public function __construct(private string $status, private array $errors = [])
    {
        if (!in_array($status, [self::STATUS_OK, self::STATUS_ERROR], true)) {
            throw new InvalidArgumentException($status . ' is not valid validation result status');
        }
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
