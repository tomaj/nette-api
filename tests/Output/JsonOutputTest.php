<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\OutputValidator\OutputValidatorResult;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\TextApiResponse;

class JsonOutputTest extends TestCase
{
    public function testSimpleValidation()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(200, ['hello' => 'world']);

        $outputValidatorResult = $output->validate($response);
        $this->assertInstanceOf(OutputValidatorResult::class, $outputValidatorResult);
        $this->assertTrue($outputValidatorResult->isOk());
        $this->assertEquals([], $outputValidatorResult->getErrors());
    }

    public function testWrongOutputSchema()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(200, ['hello', 'world']);

        $outputValidatorResult = $output->validate($response);
        $this->assertInstanceOf(OutputValidatorResult::class, $outputValidatorResult);
        $this->assertFalse($outputValidatorResult->isOk());
        $this->assertEquals(['Array value found, but an object is required'], $outputValidatorResult->getErrors());


        $output = new JsonOutput(200, '{"type": "string"}');
        $response = new JsonApiResponse(200, ['hello' => 'world']);

        $outputValidatorResult = $output->validate($response);
        $this->assertInstanceOf(OutputValidatorResult::class, $outputValidatorResult);
        $this->assertFalse($outputValidatorResult->isOk());
        $this->assertEquals(['Object value found, but a string is required'], $outputValidatorResult->getErrors());

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

        $outputValidatorResult = $output->validate($response);
        $this->assertInstanceOf(OutputValidatorResult::class, $outputValidatorResult);
        $this->assertFalse($outputValidatorResult->isOk());
        $this->assertEquals(['[Property hello] Does not have a value in the enumeration ["world","europe"]'], $outputValidatorResult->getErrors());
    }

    public function testWrongResponseCode()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(404, ['error' => ' not found']);

        $outputValidatorResult = $output->validate($response);
        $this->assertInstanceOf(OutputValidatorResult::class, $outputValidatorResult);
        $this->assertFalse($outputValidatorResult->isOk());
        $this->assertEquals(['Response code doesn\'t match'], $outputValidatorResult->getErrors());
    }

    public function testValidateOtherResponseType()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new TextApiResponse(200, 'hello world');

        $outputValidatorResult = $output->validate($response);
        $this->assertInstanceOf(OutputValidatorResult::class, $outputValidatorResult);
        $this->assertFalse($outputValidatorResult->isOk());
        $this->assertEquals([], $outputValidatorResult->getErrors());
    }
}
