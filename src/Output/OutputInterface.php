<?php

namespace Tomaj\NetteApi\Output;

use Tomaj\NetteApi\OutputValidator\OutputValidatorResult;
use Tomaj\NetteApi\Response\ResponseInterface;

interface OutputInterface
{
    public function validate(ResponseInterface $response): OutputValidatorResult;
}
