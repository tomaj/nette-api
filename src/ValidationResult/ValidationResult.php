<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\ValidationResult;

use InvalidArgumentException;

class ValidationResult implements ValidationResultInterface
{
    const STATUS_OK = 'OK';

    const STATUS_ERROR = 'error';

    private $status;

    private $errors = [];

    public function __construct(string $status, array $errors = [])
    {
        if (!in_array($status, [self::STATUS_OK, self::STATUS_ERROR], true)) {
            throw new InvalidArgumentException($status . ' is not valid validation result status');
        }

        $this->status = $status;
        $this->errors = $errors;
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
