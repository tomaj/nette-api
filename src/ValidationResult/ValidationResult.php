<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\ValidationResult;

use InvalidArgumentException;

enum ValidationStatus: string
{
    case OK = 'OK';
    case ERROR = 'error';
}

readonly class ValidationResult implements ValidationResultInterface
{
    public readonly bool $isOk {
        get => $this->status === ValidationStatus::OK;
    }

    public function __construct(
        public readonly ValidationStatus $status,
        public readonly array $errors = []
    ) {
    }

    public static function ok(): self
    {
        return new self(ValidationStatus::OK);
    }

    public static function error(array $errors = []): self
    {
        return new self(ValidationStatus::ERROR, $errors);
    }

    public function isOk(): bool
    {
        return $this->isOk;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
