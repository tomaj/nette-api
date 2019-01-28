<?php

namespace Tomaj\NetteApi\Params;

use JsonSchema\Validator;

class JsonInputParam extends InputParam
{
    const TYPE_POST_JSON  = 'POST_JSON';

    private $schemaValidator;

    private $schema;

    public function __construct($key, $schema, $required = self::OPTIONAL, $description = '')
    {
        parent::__construct(self::TYPE_POST_JSON, $key, $required, $description);

        $this->schemaValidator = new Validator();
        $this->schema = $schema;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getValue()
    {
        $input = file_get_contents("php://input");
        return json_decode($input, true);
    }

    public function isValid()
    {
        $value = $this->getValue();
        if (!$value && $this->isRequired() === self::OPTIONAL) {
            return true;
        }
        $value = json_decode(json_encode($value));
        $this->schemaValidator->validate($value, json_decode($this->schema));
        return $this->schemaValidator->isValid();
    }
}
