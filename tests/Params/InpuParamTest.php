<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Params\InputParam;

class InputParamTest extends PHPUnit_Framework_TestCase
{
	public function testValidation()
	{
		$inputParam = new InputParam(InputParam::TYPE_POST, 'mykey1', InputParam::REQUIRED);
		$_POST['mykey1'] = 'hello';
		$this->assertTrue($inputParam->isValid());
		$this->assertEquals('hello', $inputParam->getValue());
		unset($_POST['mykey1']);

		$inputParam = new InputParam(InputParam::TYPE_POST, 'mykey2', InputParam::OPTIONAL);
		$this->assertTrue($inputParam->isValid());
		$this->assertNull($inputParam->getValue());

		$inputParam = new InputParam(InputParam::TYPE_POST, 'mykey3', InputParam::REQUIRED, ['a', 'b']);
		$_POST['mykey3'] = 'hello';
		$this->assertFalse($inputParam->isValid());
		$this->assertEquals('hello', $inputParam->getValue());
		$_POST['mykey3'] = 'a';
		$this->assertTrue($inputParam->isValid());
		$this->assertEquals('a', $inputParam->getValue());
		unset($_POST['mykey3']);
	}

	/**
     * @expectedException Exception
     */
	public function testUnexpectedType()
	{
		$inputParam = new InputParam('unknown', 'mykey4', InputParam::REQUIRED, ['c', 'asdsadsad']);
		$inputParam->getValue();
	}

	public function testVariableAccess()
	{
		$inputParam = new InputParam(InputParam::TYPE_GET, 'mykey4', InputParam::REQUIRED, ['c', 'asdsadsad']);

		$this->assertEquals('GET', $inputParam->getType());
		$this->assertEquals('mykey4', $inputParam->getKey());
		$this->assertEquals(true, $inputParam->isRequired());
		$this->assertEquals(['c', 'asdsadsad'], $inputParam->getAvailableValues());
	}
}