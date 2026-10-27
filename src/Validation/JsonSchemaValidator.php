<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

use JsonSchema\Validator;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

final class JsonSchemaValidator
{
    /**
     * @param mixed $data
     */
    public function validate($data, string $schema): ValidationResultInterface
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
