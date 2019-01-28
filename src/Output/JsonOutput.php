<?php

namespace Tomaj\NetteApi\Output;

use JsonSchema\Validator;
use Tomaj\NetteApi\Response\JsonApiResponse;

class JsonOutput implements OutputInterface
{
    private $schemaValidator;

    private $code;

    private $schema;

    private $description;

    public function __construct($code, $schema, $description = null)
    {
        $this->schemaValidator = new Validator();
        $this->code = $code;
        $this->schema = $schema;
        $this->description = $description;
    }

    public function validate($response)
    {
        if (!$response instanceof JsonApiResponse) {
            return false;
        }

        if ($this->code !== $response->getCode()) {
            return false;
        }
        $value = json_decode(json_encode($response->getPayload()));
        $this->schemaValidator->validate($value, json_decode($this->schema));
        return $this->schemaValidator->isValid();
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getDescription()
    {
        return $this->description;
    }
}
