<?php

namespace Tomaj\NetteApi\Params;

class PostInputParam extends InputParam
{
    protected $type = self::TYPE_POST;

    public function getValue()
    {
        if (!filter_has_var(INPUT_POST, $this->key) && isset($_POST[$this->key])) {
            return $_POST[$this->key];
        }
        if ($this->isMulti()) {
            return filter_input(INPUT_POST, $this->key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        }
        return filter_input(INPUT_POST, $this->key);
    }
}
