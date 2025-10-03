<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Tomaj\NetteApi\Validation\InputValidator;

class GetInputParam extends InputParam
{
    protected $type = self::TYPE_GET;

    public function getValue()
    {
        if (!filter_has_var(INPUT_GET, $this->key) && isset($_GET[$this->key])) {
            return $_GET[$this->key];
        }
        $value = $this->isMulti() ? filter_input(INPUT_GET, $this->key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) : filter_input(INPUT_GET, $this->key);
        $value = $value !== null && $value !== false ? $value : $this->default;
        $inputValidator = new InputValidator();
        return $inputValidator->transformType($value, $this->valueType);
    }
}
