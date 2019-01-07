<?php

namespace Tomaj\NetteApi\Params;

class ParamsProcessor
{
    /** @var ParamInterface[] */
    private $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function isError()
    {
        foreach ($this->params as $param) {
            if (!$param->isValid()) {
                return "Invalid value for {$param->getKey()}";
            }
        }
        return false;
    }

    public function getValues()
    {
        $result = [];
        foreach ($this->params as $param) {
            $result[$param->getKey()] = $param->getValue();
        }

        return $result;
    }
}
