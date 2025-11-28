<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Tomaj\NetteApi\Validation\InputValidator;

class PostInputParam extends InputParam
{
    protected $type = self::TYPE_POST;

    public function getValue()
    {
        if (!filter_has_var(INPUT_POST, $this->key) && isset($_POST[$this->key])) {
            return $_POST[$this->key];
        }
        $value = $this->isMulti() ? filter_input(INPUT_POST, $this->key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) : filter_input(INPUT_POST, $this->key);
        $value = $value !== null && $value !== false ? $value : $this->default;
        $inputValidator = new InputValidator();
        return $inputValidator->transformType($value, $this->valueType);
    }
}
