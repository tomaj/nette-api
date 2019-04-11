<?php

namespace Tomaj\NetteApi\Params;

class PutInputParam extends InputParam
{
    protected $type = self::TYPE_PUT;

    public function getValue()
    {
        parse_str(file_get_contents("php://input"), $params);
        return $params[$this->key] ?? null;
    }
}
