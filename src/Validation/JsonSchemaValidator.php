<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

use InvalidArgumentException;
use JsonSchema\Validator;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

final class JsonSchemaValidator
{
    /**
     * Checks that the schema is a JSON object (or true/false), so a broken schema fails when the handler is defined,
     * not by accepting every request.
     *
     * @param string $where what uses the schema, for example "JsonInputParam 'data'"
     * @throws InvalidArgumentException
     */
    public static function checkSchema(string $schema, string $where): void
    {
        try {
            $decoded = json_decode($schema, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException(sprintf(
                'The JSON schema of %s is not valid JSON (%s). Pass the schema as a JSON string, for example \'{"type": "object", "properties": {"name": {"type": "string"}}}\', or load it with file_get_contents().',
                $where,
                $e->getMessage(),
            ), previous: $e);
        }
        if (!is_object($decoded) && !is_bool($decoded)) {
            throw new InvalidArgumentException(sprintf(
                'The JSON schema of %s is a JSON %s, but a schema is a JSON object. Use for example \'{"type": "object"}\'.',
                $where,
                get_debug_type($decoded),
            ));
        }
    }

    public function validate(mixed $data, string $schema): ValidationResultInterface
    {
        $schemaValidator = new Validator();
        $schemaValidator->validate($data, json_decode($schema));

        if ($schemaValidator->isValid()) {
            return new ValidationResult(ValidationResult::STATUS_OK);
        }

        $errors = [];
        foreach ($schemaValidator->getErrors() as $error) {
            $error = TypeHelper::toArray($error);
            $prefix = empty($error['property']) ? '' : '[Property ' . TypeHelper::toString($error['property']) . '] ';
            $errors[] = $prefix . TypeHelper::toString($error['message'] ?? '');
        }

        return new ValidationResult(ValidationResult::STATUS_ERROR, $errors);
    }
}
