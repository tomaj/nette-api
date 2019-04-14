<?php

namespace Tomaj\NetteApi\Output;

use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

interface OutputInterface
{
    public function validate(ResponseInterface $response): ValidationResultInterface;
}
