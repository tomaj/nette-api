<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Validation\InputValidator;

/**
 * One field of a JSON or form body. Typed handlers generate these; params() can declare them directly.
 */
class BodyInputParam extends InputParam
{
    protected $type = self::TYPE_BODY;

    #[\Override]
    public function getValue(): mixed
    {
        $value = $_POST[$this->getKey()] ?? null;
        if ($value === null && MediaTypes::normalize(is_string($_SERVER['CONTENT_TYPE'] ?? null) ? $_SERVER['CONTENT_TYPE'] : null) === MediaTypes::JSON) {
            $decoded = self::json();
            if (is_array($decoded)) {
                $value = $decoded[$this->getKey()] ?? null;
            }
        }
        $value ??= $this->default;

        return new InputValidator()->transformType($value, $this->valueType, $this->isRequired());
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function json(): ?array
    {
        $raw = RequestInput::rawContents();
        $raw = is_string($raw) ? $raw : '';
        $decoded = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE || !$decoded instanceof \stdClass) {
            return null;
        }
        $body = [];
        foreach (get_object_vars($decoded) as $key => $value) {
            $body[$key] = $value;
        }

        return $body;
    }
}
