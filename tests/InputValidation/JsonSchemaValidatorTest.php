<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\InputValidation;

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
}