<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Params\InputParam;
use Exception;

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

        $inputParam = new InputParam(InputParam::TYPE_GET, 'asdsd');
        $this->assertNull($inputParam->getValue());
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

    public function testNotFoundFileType()
    {
        $inputParam = new InputParam(InputParam::TYPE_FILE, 'myfile', InputParam::REQUIRED);
        $this->assertNull($inputParam->getValue());
        $this->assertFalse($inputParam->isValid());
    }

    public function testFileInputType()
    {
        $_FILES['myfile'] = 'hello';
        $inputParam = new InputParam(InputParam::TYPE_FILE, 'myfile', InputParam::REQUIRED);
        $this->assertEquals('hello', $inputParam->getValue());
    }

    public function testMultiFileInput()
    {
        $_FILES['myfile'] = [
            'name' => ['file1', 'file2'],
            'type' => ['text/plain', 'image/jpeg'],
            'tmp_name' => ['/tmp/1', '/tmp/2'],
            'size' => [101, 102],
        ];

        $inputParam = new InputParam(InputParam::TYPE_FILE, 'myfile', InputParam::REQUIRED, null, true);
        $value = $inputParam->getValue();

        $this->assertCount(2, $value);

        $this->assertEquals('file1', $value[0]['name']);
        $this->assertEquals('text/plain', $value[0]['type']);
        $this->assertEquals('/tmp/1', $value[0]['tmp_name']);
        $this->assertEquals(101, $value[0]['size']);

        $this->assertEquals('file2', $value[1]['name']);
        $this->assertEquals('image/jpeg', $value[1]['type']);
        $this->assertEquals('/tmp/2', $value[1]['tmp_name']);
        $this->assertEquals(102, $value[1]['size']);
    }

    public function testCookiesValues()
    {
        $_COOKIE['mykey'] = 'asd';
        $inputParam = new InputParam(InputParam::TYPE_COOKIE, 'mykey', InputParam::REQUIRED);
        $this->assertEquals('asd', $inputParam->getValue());
    }

    public function testStaticAvailableValues()
    {
        $_GET['dsgerg'] = 'asfsaf';
        $inputParam = new InputParam(InputParam::TYPE_GET, 'dsgerg', InputParam::REQUIRED, 'vgdgr');
        $this->assertFalse($inputParam->isValid());

        $_GET['dsgerg'] = 'vgdgr';
        $this->assertTrue($inputParam->isValid());
    }
}
