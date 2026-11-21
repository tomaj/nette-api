<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Validation\InputValidator;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

/**
 * One field of a JSON or form body. Typed handlers generate these; params() can declare them directly.
 * The body is parsed like a typed handler parses it (malformed JSON is an error and another media type is 415),
 * the value is then converted like the other params() params, and a JSON object is an array.
 */
class BodyInputParam extends InputParam
{
    protected $type = self::TYPE_BODY;

    #[\Override]
    public function getValue(): mixed
    {
        $body = $this->body()['body'];
        // an explicit null stays null, only a missing field takes the default
        $value = array_key_exists($this->getKey(), $body) ? $body[$this->getKey()] : $this->default;

        return new InputValidator()->transformType(self::toArrays($value), $this->valueType, $this->isRequired());
    }

    /**
     * The typed body keeps JSON objects as stdClass; a params() handler reads arrays, as from JsonInputParam.
     */
    private static function toArrays(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        return is_array($value) ? array_map(self::toArrays(...), $value) : $value;
    }

    #[\Override]
    public function validate(): ValidationResultInterface
    {
        $failure = $this->body()['failure'];
        if ($failure !== null) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, [$failure]);
        }

        return parent::validate();
    }

    /**
     * @return array{body: array<array-key, mixed>, failure: ?string}
     */
    private function body(): array
    {
        return RequestInput::plainBody($this->getContentTypes() ?: MediaTypes::ALL);
    }
}
