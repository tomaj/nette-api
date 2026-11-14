<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Tomaj\NetteApi\Validation\InputValidator;

/**
 * A value taken from an HTTP header. The lookup is case-insensitive.
 */
class HeaderInputParam extends InputParam
{
    protected $type = self::TYPE_HEADER;

    #[\Override]
    public function getValue(): mixed
    {
        $value = HeaderBag::has($this->getKey()) ? HeaderBag::get($this->getKey()) : $this->default;
        return new InputValidator()->transformType($value, $this->valueType, $this->isRequired());
    }
}
