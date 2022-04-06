<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output;

use Tomaj\NetteApi\Response\RedirectResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

class RedirectOutput extends AbstractOutput
{
    public function validate(ResponseInterface $response): ValidationResultInterface
    {
        if (!$response instanceof RedirectResponse) {
            return new ValidationResult(ValidationResult::STATUS_ERROR);
        }
        if ($this->code !== $response->getCode()) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, ['Response code doesn\'t match']);
        }
        return new ValidationResult(ValidationResult::STATUS_OK);
    }
}
