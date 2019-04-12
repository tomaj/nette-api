<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\TextApiResponse;

class JsonOutputTest extends TestCase
{
    public function testSimpleValidation()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(200, ['hello' => 'world']);

        $this->assertTrue($output->validate($response));
        $this->assertEquals([], $output->getErrors());
    }

    public function testWrongOutputSchema()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(200, ['hello', 'world']);

        $this->assertFalse($output->validate($response));
        $this->assertEquals(['Array value found, but an object is required'], $output->getErrors());

        $output = new JsonOutput(200, '{"type": "string"}');
        $response = new JsonApiResponse(200, ['hello' => 'world']);

        $this->assertFalse($output->validate($response));
        $this->assertEquals(['Object value found, but a string is required'], $output->getErrors());

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

        $this->assertFalse($output->validate($response));
        $this->assertEquals(['[Property hello] Does not have a value in the enumeration ["world","europe"]'], $output->getErrors());
    }

    public function testWrongResponseCode()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new JsonApiResponse(404, ['error' => ' not found']);

        $this->assertFalse($output->validate($response));
        $this->assertEquals(['Response code doesn\'t match'], $output->getErrors());
    }

    public function testValidateOtherResponseType()
    {
        $output = new JsonOutput(200, '{"type": "object"}');
        $response = new TextApiResponse(200, 'hello world');

        $this->assertFalse($output->validate($response));
        $this->assertEquals([], $output->getErrors());
    }
}
