<?php

namespace Tomaj\NetteApi\Output;

use JsonSchema\Validator;
use Tomaj\NetteApi\OutputValidator\OutputValidatorResult;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

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

    public function validate(ResponseInterface $response): OutputValidatorResult
    {
        if (!$response instanceof JsonApiResponse) {
            return new OutputValidatorResult(OutputValidatorResult::STATUS_ERROR);
        }
        if ($this->code !== $response->getCode()) {
            return new OutputValidatorResult(OutputValidatorResult::STATUS_ERROR, ['Response code doesn\'t match']);
        }

        $value = json_decode(json_encode($response->getPayload()));
        $this->schemaValidator->validate($value, json_decode($this->schema));

        if ($this->schemaValidator->isValid()) {
            return new OutputValidatorResult(OutputValidatorResult::STATUS_OK);
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

        return new OutputValidatorResult(OutputValidatorResult::STATUS_ERROR, $errors);
    }
}
