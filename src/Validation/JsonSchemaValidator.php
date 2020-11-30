<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

use JsonSchema\Validator;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

class JsonSchemaValidator
{
    /**
     * @param mixed $data
     * @param string $schema
     * @return ValidationResultInterface
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
            $errorMessage = '';
            if ($error['property']) {
                $errorMessage .= '[Property ' . $error['property'] . '] ';
            }
            $errorMessage .= $error['message'];
            $errors[] = $errorMessage;
        }
        return new ValidationResult(ValidationResult::STATUS_ERROR, $errors);
    }
}
