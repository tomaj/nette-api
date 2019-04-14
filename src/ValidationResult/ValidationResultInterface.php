<?php

namespace Tomaj\NetteApi\ValidationResult;

interface ValidationResultInterface
{
    public function isOk(): bool;

    public function getErrors(): array;
}
