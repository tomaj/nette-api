<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Tomaj\NetteApi\ValidationResult\FieldErrors;

final class ParamsProcessor
{
    /** @var array<mixed> */
    private array $errors = [];

    /**
     * @param ParamInterface[] $params
     */
    public function __construct(private readonly array $params)
    {
    }

    public function isError(): bool
    {
        foreach ($this->params as $param) {
            $validationResult = $param->validate();
            if (!$validationResult->isOk()) {
                if ($validationResult instanceof FieldErrors) {
                    foreach ($validationResult->fieldErrors() as $path => $messages) {
                        $this->errors[$path] = $messages;
                    }
                    continue;
                }
                $this->errors[$param->getKey()] = $validationResult->getErrors();
            }
        }

        return !empty($this->errors);
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<mixed>
     */
    public function getValues(): array
    {
        $result = [];
        foreach ($this->params as $param) {
            $result[$param->getKey()] = $param->getValue();
        }

        return $result;
    }
}
