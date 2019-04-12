<?php

namespace Tomaj\NetteApi\Output;

abstract class Output implements OutputInterface
{
    protected $errors = [];

    final public function getErrors(): array
    {
        return $this->errors;
    }
}
