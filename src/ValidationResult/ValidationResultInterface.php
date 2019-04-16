<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\ValidationResult;

interface ValidationResultInterface
{
    public function isOk(): bool;

    public function getErrors(): array;
}
