<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\ValidationResult;

/**
 * Field-path errors produced by a typed DTO. Keys are paths such as items[1].quantity.
 */
final readonly class FieldErrors implements ValidationResultInterface
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(private array $errors)
    {
    }

    #[\Override]
    public function isOk(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, list<string>>
     */
    #[\Override]
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return $this->errors;
    }
}
