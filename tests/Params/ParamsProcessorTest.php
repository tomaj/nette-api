<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Params\PutInputParam;

class ParamsProcessorTest extends TestCase
{
    public function testError()
    {
        $processor = new ParamsProcessor([
            (new PostInputParam('mykey1'))->setRequired(),
        ]);

        $this->assertTrue($processor->isError());
        $this->assertEquals(['mykey1' => ['Field is required']], $processor->getErrors());

        $_GET['mykey2'] = 'x';
        $processor = new ParamsProcessor([
            (new GetInputParam('mykey2'))->setRequired()->setAvailableValues(['a', 'b', 'c']),
        ]);

        $this->assertTrue($processor->isError());
        $this->assertEquals(['mykey2' => ['Field contains not available value(s)']], $processor->getErrors());
    }

    public function testPass()
    {
        $_POST['mykey1'] = 'hello';
        $_GET['mykey2'] = 'asdasd';
        $_POST['mykey3'] = 'asd';

        $processor = new ParamsProcessor([
            (new PostInputParam('mykey1'))->setRequired(),
            (new GetInputParam('mykey2'))->setRequired(),
            new PostInputParam('mykey3'),
        ]);

        $this->assertFalse($processor->isError());
        $this->assertEquals([], $processor->getErrors());

        $this->assertEquals($processor->getValues(), [
            'mykey1' => 'hello',
            'mykey2' => 'asdasd',
            'mykey3' => 'asd',
        ]);
    }

    public function testOptionalDefaultValue()
    {
        $processor = new ParamsProcessor([
            new PostInputParam('mykey10'),
            new GetInputParam('mykey20'),
            new PutInputParam('mykey30'),
        ]);

        $this->assertEquals($processor->getValues(), [
            'mykey10' => null,
            'mykey20' => null,
            'mykey30' => null,
        ]);
    }
}
