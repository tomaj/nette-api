<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Params\InputParam;

class ParamsProcessorTest extends PHPUnit_Framework_TestCase
{
    public function testError()
    {
        $processor = new ParamsProcessor([
            new InputParam(InputParam::TYPE_POST, 'mykey1', InputParam::REQUIRED),
        ]);

        $this->assertEquals("Invalid value for mykey1", $processor->isError());
    }

    public function testPass()
    {
        $_POST['mykey1'] = 'hello';
        $_GET['mykey2'] = 'asdasd';
        $_POST['mykey3'] = 'asd';

        $processor = new ParamsProcessor([
            new InputParam(InputParam::TYPE_POST, 'mykey1', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_GET, 'mykey2', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'mykey3', InputParam::OPTIONAL),
        ]);

        $this->assertFalse($processor->isError());

        $this->assertEquals($processor->getValues(), [
            'mykey1' => 'hello',
            'mykey2' => 'asdasd',
            'mykey3' => 'asd',
        ]);
    }

    public function testOptionalDefaultValue()
    {
        $processor = new ParamsProcessor([
            new InputParam(InputParam::TYPE_POST, 'mykey10', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_GET, 'mykey20', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_PUT, 'mykey30', InputParam::OPTIONAL),
        ]);

        $this->assertEquals($processor->getValues(), [
            'mykey10' => null,
            'mykey20' => null,
            'mykey30' => null,
        ]);
    }
}
