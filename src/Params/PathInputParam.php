<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Tomaj\NetteApi\Validation\InputType;
use Tomaj\NetteApi\Validation\InputValidator;

/**
 * A value taken from a {name} segment of the endpoint URL. Path parameters are required.
 */
class PathInputParam extends InputParam
{
    protected $type = self::TYPE_PATH;

    /**
     * @param InputType|string|null $valueType expected value type, see InputType
     */
    public function __construct(string $key, InputType|string|null $valueType = null)
    {
        parent::__construct($key, $valueType);
        $this->setRequired();
    }

    #[\Override]
    public function getValue(): mixed
    {
        $value = PathValues::has($this->getKey()) ? PathValues::get($this->getKey()) : $this->default;
        return new InputValidator()->transformType($value, $this->valueType, $this->isRequired());
    }
}
