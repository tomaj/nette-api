<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

class PutInputParam extends InputParam
{
    protected $type = self::TYPE_PUT;

    public function getValue(): mixed
    {
        $values = file_get_contents('php://input');
        parse_str($values ?: '', $params);
        return $params[$this->key] ?? $this->default;
    }
}
