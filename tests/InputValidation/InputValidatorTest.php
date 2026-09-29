<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\InputValidation;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Validation\InputType;
use Tomaj\NetteApi\Validation\InputValidator;

class InputValidatorTest extends TestCase
{
	private InputValidator $validator;

	protected function setUp(): void
	{
		$this->validator = new InputValidator();
	}

	public function testValidateAcceptsNullAndUnspecifiedTypes(): void
	{
		self::assertTrue($this->validator->validate(null, InputType::STRING)->isOk());
		self::assertTrue($this->validator->validate('value')->isOk());
	}

	public function testValidateAcceptsSupportedTypes(): void
	{
		$validValues = [
			InputType::BOOLEAN => true,
			InputType::INTEGER => 12,
			InputType::DOUBLE => 12.5,
			InputType::FLOAT => 12,
			InputType::STRING => 'value',
			InputType::ARRAY => ['value'],
		];

		foreach ($validValues as $type => $value) {
			self::assertTrue($this->validator->validate($value, $type)->isOk(), 'Expected ' . $type . ' to be valid.');
		}
	}

	public function testValidateRejectsValuesWithTheWrongType(): void
	{
		$result = $this->validator->validate('12', InputType::INTEGER);

		self::assertFalse($result->isOk());
		self::assertSame(['Value 12 has invalid type. Expected integer.'], $result->getErrors());
	}

	public function testValidateRejectsInvalidValuesForEachRemainingType(): void
	{
		$invalidValues = [
			InputType::BOOLEAN => ['not boolean', 'boolean'],
			InputType::DOUBLE => ['not numeric', 'double'],
			InputType::FLOAT => ['not numeric', 'float'],
			InputType::ARRAY => ['not an array', 'array'],
		];

		foreach ($invalidValues as $type => [$value, $expectedType]) {
			$result = $this->validator->validate($value, $type);

			self::assertFalse($result->isOk());
			self::assertSame(['Value ' . $value . ' has invalid type. Expected ' . $expectedType . '.'], $result->getErrors());
		}
	}

	public function testValidateChecksEveryArrayItem(): void
	{
		self::assertTrue($this->validator->validate(['first', 'second'], InputType::STRING)->isOk());

		$result = $this->validator->validate(['first', 2], InputType::STRING);

		self::assertFalse($result->isOk());
		self::assertSame(['Value 2 has invalid type. Expected string.'], $result->getErrors());
	}

	public function testValidateRejectsUnknownTypes(): void
	{
		$result = $this->validator->validate('value', 'unknown');

		self::assertFalse($result->isOk());
		self::assertSame(['Value value has invalid type.'], $result->getErrors());
	}

	public function testTransformTypeReturnsUnchangedValueWhenNoConversionIsRequested(): void
	{
		self::assertSame('value', $this->validator->transformType('value'));
		self::assertNull($this->validator->transformType(null, InputType::STRING));
		self::assertSame(['value'], $this->validator->transformType(['value'], InputType::ARRAY));
	}

	public function testTransformTypeConvertsSupportedScalarTypes(): void
	{
		self::assertTrue($this->validator->transformType('TRUE', InputType::BOOLEAN));
		self::assertTrue($this->validator->transformType('1', InputType::BOOLEAN));
		self::assertFalse($this->validator->transformType('yes', InputType::BOOLEAN));
		self::assertSame(12, $this->validator->transformType('12', InputType::INTEGER));
		self::assertSame(12.5, $this->validator->transformType('12.5', InputType::DOUBLE));
		self::assertSame(12.5, $this->validator->transformType('12.5', InputType::FLOAT));
		self::assertSame('12', $this->validator->transformType('12', InputType::STRING));
	}

	public function testTransformTypeReturnsNullForInvalidNumericValues(): void
	{
		self::assertNull($this->validator->transformType('not numeric', InputType::INTEGER));
		self::assertNull($this->validator->transformType('not numeric', InputType::DOUBLE));
		self::assertNull($this->validator->transformType('not numeric', InputType::FLOAT));
	}

	public function testTransformTypeHandlesRequiredEmptyValues(): void
	{
		self::assertNull($this->validator->transformType('', InputType::INTEGER, true));
		self::assertNull($this->validator->transformType('', InputType::DOUBLE, true));
		self::assertNull($this->validator->transformType('', InputType::FLOAT, true));
		self::assertNull($this->validator->transformType('', InputType::STRING, true));
		self::assertSame(12, $this->validator->transformType('12', InputType::INTEGER, true));
		self::assertSame('', $this->validator->transformType('', InputType::STRING));
	}

	public function testTransformTypePreservesArrayKeysAndConvertsItems(): void
	{
		self::assertSame(
			['first' => 1, 'second' => 2],
			$this->validator->transformType(['first' => '1', 'second' => '2'], InputType::INTEGER)
		);
	}
}
