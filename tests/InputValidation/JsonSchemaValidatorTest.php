<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\InputValidation;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Validation\JsonSchemaValidator;

class JsonSchemaValidatorTest extends TestCase
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

		self::assertTrue($result->isOk());
		self::assertSame([], $result->getErrors());
	}

	public function testValidateReturnsRootErrorWithoutPropertyPrefix(): void
	{
		$result = $this->validator->validate(['hello', 'world'], '{"type":"object"}');

		self::assertFalse($result->isOk());
		self::assertSame(['Array value found, but an object is required'], $result->getErrors());
	}

	public function testValidatePrefixesPropertyErrors(): void
	{
		$result = $this->validator->validate(
			(object) ['hello' => 'space'],
			'{"type":"object","properties":{"hello":{"type":"string","enum":["world","europe"]}}}'
		);

		self::assertFalse($result->isOk());
		self::assertSame(
			['[Property hello] Does not have a value in the enumeration ["world","europe"]'],
			$result->getErrors()
		);
	}

	public function testValidateCollectsMultiplePropertyErrors(): void
	{
		$result = $this->validator->validate(
			(object) ['first' => 'invalid', 'second' => 'invalid'],
			'{"type":"object","properties":{"first":{"type":"string","enum":["valid"]},"second":{"type":"string","enum":["valid"]}}}'
		);

		self::assertFalse($result->isOk());
		self::assertCount(2, $result->getErrors());
		self::assertSame(
			[
				'[Property first] Does not have a value in the enumeration ["valid"]',
				'[Property second] Does not have a value in the enumeration ["valid"]',
			],
			$result->getErrors()
		);
	}
}