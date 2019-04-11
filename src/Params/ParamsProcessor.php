<?php

namespace Tomaj\NetteApi\Params;

class ParamsProcessor
{
    /** @var ParamInterface[] */
    private $params;

    private $errors = [];

    /**
     * @param ParamInterface[] $params
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function isError(): bool
    {
        foreach ($this->params as $param) {
            if (!$param->isValid()) {
                $this->errors[$param->getKey()] = $param->getErrors();
            }
        }
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValues(): array
    {
        $result = [];
        foreach ($this->params as $param) {
            $result[$param->getKey()] = $param->getValue();
        }

        return $result;
    }
}
