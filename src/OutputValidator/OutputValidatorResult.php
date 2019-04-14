<?php

namespace Tomaj\NetteApi\OutputValidator;

class OutputValidatorResult
{
    const STATUS_OK = 'ok';

    const STATUS_ERROR = 'error';

    private $status;

    private $errors = [];

    public function __construct(string $status, array $errors = [])
    {
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
