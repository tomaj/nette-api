<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Output;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\TextApiResponse;

class JsonOutputTest extends TestCase
{
    public function testSimpleValidation()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(200, ['hello' => 'world']);

        $validationResult = $output->validate($response);
        $this->assertInstanceOf(ValidationResultInterface::class, $validationResult);
        $this->assertTrue($validationResult->isOk());
        $this->assertEquals([], $validationResult->getErrors());
    }

    public function testWrongOutputSchema()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(200, ['hello', 'world']);

        $validationResult = $output->validate($response);
        $this->assertInstanceOf(ValidationResultInterface::class, $validationResult);
        $this->assertFalse($validationResult->isOk());
        $this->assertEquals(['Array value found, but an object is required'], $validationResult->getErrors());


        $output = new JsonOutput(200, '{"type": "string"}');
        $response = new JsonApiResponse(200, ['hello' => 'world']);

        $validationResult = $output->validate($response);
        $this->assertInstanceOf(ValidationResultInterface::class, $validationResult);
        $this->assertFalse($validationResult->isOk());
        $this->assertEquals(['Object value found, but a string is required'], $validationResult->getErrors());

        $schema = [
            'type' => 'object',
            'properties' => [
                'hello' => [
                    'type' => 'string',
                    'enum' => ['world', 'europe']
                ],
            ],
            'required' => ['hello']
        ];
        $output = new JsonOutput(200, json_encode($schema));
        $response = new JsonApiResponse(200, ['hello' => 'space']);

        $validationResult = $output->validate($response);
        $this->assertInstanceOf(ValidationResultInterface::class, $validationResult);
        $this->assertFalse($validationResult->isOk());
        $this->assertEquals(['[Property hello] Does not have a value in the enumeration ["world","europe"]'], $validationResult->getErrors());
    }

    public function testWrongResponseCode()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(404, ['error' => ' not found']);

        $validationResult = $output->validate($response);
        $this->assertInstanceOf(ValidationResultInterface::class, $validationResult);
        $this->assertFalse($validationResult->isOk());
        $this->assertEquals(['Response code doesn\'t match'], $validationResult->getErrors());
    }

    public function testValidateOtherResponseType()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new TextApiResponse(200, 'hello world');

        $validationResult = $output->validate($response);
        $this->assertInstanceOf(ValidationResultInterface::class, $validationResult);
        $this->assertFalse($validationResult->isOk());
        $this->assertEquals([], $validationResult->getErrors());
    }
}
