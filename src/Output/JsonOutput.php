<?php

namespace Tomaj\NetteApi\Output;

use JsonSchema\Validator;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class JsonOutput extends Output
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

    public function validate(ResponseInterface $response): bool
    {
        if (!$response instanceof JsonApiResponse) {
            return false;
        }
        if ($this->code !== $response->getCode()) {
            $this->errors[] = 'Response code doesn\'t match';
            return false;
        }

        $value = json_decode(json_encode($response->getPayload()));
        $this->schemaValidator->validate($value, json_decode($this->schema));

        foreach ($this->schemaValidator->getErrors() as $error) {
            $errorMessage = '';
            if ($error['property']) {
                $errorMessage .= '[Property ' . $error['property'] . '] ';
            }
            $errorMessage .= $error['message'];
            $this->errors[] = $errorMessage;
        }

        return $this->schemaValidator->isValid();
    }
}
