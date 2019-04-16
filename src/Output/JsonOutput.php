<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output;

use JsonSchema\Validator;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

class JsonOutput implements OutputInterface
{
    private $schemaValidator;

    private $code;

    private $schema;

    public function __construct(int $code, $schema)
    {
        $this->schemaValidator = new Validator();
        $this->code = $code;
        $this->schema = $schema;
    }

    public function validate(ResponseInterface $response): ValidationResultInterface
    {
        if (!$response instanceof JsonApiResponse) {
            return new ValidationResult(ValidationResult::STATUS_ERROR);
        }
        if ($this->code !== $response->getCode()) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, ['Response code doesn\'t match']);
        }

        $value = json_decode(json_encode($response->getPayload()));
        $this->schemaValidator->validate($value, json_decode($this->schema));

        if ($this->schemaValidator->isValid()) {
            return new ValidationResult(ValidationResult::STATUS_OK);
        }

        $errors = [];
        foreach ($this->schemaValidator->getErrors() as $error) {
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
