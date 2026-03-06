<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

class CookieInputParam extends InputParam
{
    protected $type = self::TYPE_COOKIE;

    public function getValue(): mixed
    {
        if (!filter_has_var(INPUT_COOKIE, $this->key) && isset($_COOKIE[$this->key])) {
            return $_COOKIE[$this->key];
        }

        $value = filter_input(INPUT_COOKIE, $this->key);
        return $value ?? $this->default;
    }
}
