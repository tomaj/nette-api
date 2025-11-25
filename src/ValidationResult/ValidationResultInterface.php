<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\ValidationResult;

interface ValidationResultInterface
{
    public function isOk(): bool;

    /**
     * @return array<mixed>
     */
    public function getErrors(): array;
}
