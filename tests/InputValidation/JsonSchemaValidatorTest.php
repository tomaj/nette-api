<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\InputValidation;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Output\JsonOutput;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Validation\JsonSchemaValidator;

final class JsonSchemaValidatorTest extends TestCase
{
	private JsonSchemaValidator $validator;

	protected function setUp(): void
	{
		$this->validator = new JsonSchemaValidator();
	}

	public function testValidateReturnsSuccessForValidData(): void
	{
		$result = $this->validator->validate(
			(object) ['name' => 'Ada'],
			'{"type":"object","properties":{"name":{"type":"string"}},"required":["name"]}'
		);

		$this->assertTrue($result->isOk());
		$this->assertSame([], $result->getErrors());
	}

	public function testValidateReturnsRootErrorWithoutPropertyPrefix(): void
	{
		$result = $this->validator->validate(['hello', 'world'], '{"type":"object"}');

		$this->assertFalse($result->isOk());
		$this->assertSame(['Array value found, but an object is required'], $result->getErrors());
	}

	public function testValidatePrefixesPropertyErrors(): void
	{
		$result = $this->validator->validate(
			(object) ['hello' => 'space'],
			'{"type":"object","properties":{"hello":{"type":"string","enum":["world","europe"]}}}'
		);

		$this->assertFalse($result->isOk());
		$this->assertSame(['[Property hello] Does not have a value in the enumeration ["world","europe"]'], $result->getErrors());
	}

	public function testValidateCollectsMultiplePropertyErrors(): void
	{
		$result = $this->validator->validate(
			(object) ['first' => 'invalid', 'second' => 'invalid'],
			'{"type":"object","properties":{"first":{"type":"string","enum":["valid"]},"second":{"type":"string","enum":["valid"]}}}'
		);

		$this->assertFalse($result->isOk());
		$this->assertCount(2, $result->getErrors());
		$this->assertSame([
				'[Property first] Does not have a value in the enumeration ["valid"]',
				'[Property second] Does not have a value in the enumeration ["valid"]',
			], $result->getErrors());
	}

	#[DoesNotPerformAssertions]
    public function testCheckSchemaAcceptsObjectAndBoolean(): void
	{
		JsonSchemaValidator::checkSchema('{"type": "object"}', 'x');
		JsonSchemaValidator::checkSchema('true', 'x');
	}

	public function testCheckSchemaRejectsInvalidJson(): void
	{
		// the json_decode() detail differs between PHP versions (8.6 adds the location), so match around it
		try {
			JsonSchemaValidator::checkSchema('{type: object}', "JsonInputParam 'data'");
			$this->fail('An invalid schema must throw');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringStartsWith('The JSON schema of JsonInputParam \'data\' is not valid JSON (Syntax error', $e->getMessage());
			$this->assertStringEndsWith('). Pass the schema as a JSON string, for example \'{"type": "object", "properties": {"name": {"type": "string"}}}\', or load it with file_get_contents().', $e->getMessage());
			$this->assertInstanceOf(\JsonException::class, $e->getPrevious());
		}
	}

	public function testCheckSchemaRejectsNonObject(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('The JSON schema of JsonOutput 200 is a JSON string, but a schema is a JSON object. Use for example \'{"type": "object"}\'.');
		JsonSchemaValidator::checkSchema('"object"', 'JsonOutput 200');
	}

	public function testJsonInputParamAndJsonOutputCheckTheSchema(): void
	{
		try {
			new JsonInputParam('data', '');
			$this->fail('no exception');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringStartsWith("The JSON schema of JsonInputParam 'data' is not valid JSON", $e->getMessage());
		}
		$this->expectExceptionMessage('The JSON schema of JsonOutput 201 is not valid JSON');
		new JsonOutput(201, '{');
	}
}
