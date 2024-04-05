<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output;

use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\JsonSchemaValidator;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

class JsonOutput extends AbstractOutput
{
    private $schema;

    /** @var mixed */
    private $example;

    /** @var array 
     * 
     * 
    */
    private $additionalExamples = [];

    public function __construct(int $code, string $schema, string $description = '')
    {
        parent::__construct($code, $description);
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

        $schemaValidator = new JsonSchemaValidator();
        return $schemaValidator->validate($value, $this->schema);
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * @param mixed $example
     */
    public function setExample($example): self
    {
        $this->example = $example;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExample()
    {
        return $this->example;
    }

    /**
     * Set multiple examples for request. This is useful for testing. 
     * Associative names will be used as example name. 
     * [
     *  "A" => [ "param1" => "value1", "param2" => "value2" ],
     *  "B" => [ "param1" => "value3", "param2" => "value4" ]
     * ]
     * @param array $examples
     * @return self
     */
    public function setAdditionalExamples(array $examples): self
    {
        if (empty($this->example)) {
            throw new \Exception('You have to set example before you can set additional examples');
        }
        foreach ($examples as &$example) {
            if (!is_array($example)) {
                $example = json_decode($example, true);
            }
        }
        $this->additionalExamples = $examples;
        return $this;
    }

    /**
     * Get additional examples
     * @return array
     */
    public function getAdditionalExamples(): array  
    {
        return $this->additionalExamples;
    }
}
