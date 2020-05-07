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
}
