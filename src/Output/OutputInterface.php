<?php

namespace Tomaj\NetteApi\Output;

use Tomaj\NetteApi\Response\ResponseInterface;

interface OutputInterface
{
    public function validate(ResponseInterface $response): bool;

    public function getErrors(): array;
}
