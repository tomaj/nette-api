<?php

namespace Tomaj\NetteApi\Test\Params;

use Exception;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Params\CookieInputParam;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Params\PutInputParam;
use Tomaj\NetteApi\Params\RawInputParam;

class InputParamTest extends TestCase
{
    public function testValidation()
    {
        $inputParam = (new PostInputParam('mykey1'))->setRequired();
        $_POST['mykey1'] = 'hello';
        $this->assertTrue($inputParam->isValid());
        $this->assertEquals('hello', $inputParam->getValue());
        unset($_POST['mykey1']);

        $inputParam = new PostInputParam('mykey2');
        $this->assertTrue($inputParam->isValid());
        $this->assertNull($inputParam->getValue());

        $inputParam = (new PostInputParam('mykey3'))->setRequired()->setAvailableValues(['a', 'b']);
        $_POST['mykey3'] = 'hello';
        $this->assertFalse($inputParam->isValid());
        $this->assertEquals('hello', $inputParam->getValue());
        $_POST['mykey3'] = 'a';
        $this->assertTrue($inputParam->isValid());
        $this->assertEquals('a', $inputParam->getValue());
        unset($_POST['mykey3']);

        $inputParam = new GetInputParam('asdsd');
        $this->assertNull($inputParam->getValue());

        $inputParam = new JsonInputParam('json', '{}');
        $this->assertFalse($inputParam->isValid());
        $this->assertNull($inputParam->getValue());
        $this->assertEquals(['Syntax error'], $inputParam->getErrors());

        $inputParam = (new JsonInputParam('json', '{"type": "object"}'))->setDefault('{}');
        $this->assertTrue($inputParam->isValid());
        $this->assertEquals([], $inputParam->getValue());
        $this->assertEquals([], $inputParam->getErrors());

        $inputParam = (new JsonInputParam('json', '{"type": "string"}'))->setDefault('{"hello": "world"}');
        $this->assertFalse($inputParam->isValid());
        $this->assertEquals(['hello' => 'world'], $inputParam->getValue());
        $this->assertEquals(['Object value found, but a string is required'], $inputParam->getErrors());
    }

    public function testVariableAccess()
    {
        $inputParam = (new GetInputParam('mykey4'))->setRequired()->setAvailableValues(['c', 'asdsadsad']);

        $this->assertEquals('GET', $inputParam->getType());
        $this->assertEquals('mykey4', $inputParam->getKey());
        $this->assertEquals(true, $inputParam->isRequired());
        $this->assertEquals(['c', 'asdsadsad'], $inputParam->getAvailableValues());
    }

    public function testNotFoundFileType()
    {
        $inputParam = (new FileInputParam('myfile'))->setRequired();
        $this->assertNull($inputParam->getValue());
        $this->assertFalse($inputParam->isValid());
    }

    public function testGetInputType()
    {
        $inputParam = (new GetInputParam('mykey'))->setRequired();
        $this->assertNull($inputParam->getValue());
        $this->assertEquals('', $inputParam->getDescription());

        $inputParam = (new GetInputParam('mykey'))->setMulti()->setDescription('mykey description');
        $this->assertNull($inputParam->getValue());
        $this->assertEquals('mykey description', $inputParam->getDescription());

        $_GET['mykey'] = 'asd';
        $inputParam = (new GetInputParam('mykey'))->setRequired();
        $this->assertEquals('asd', $inputParam->getValue());
    }

    public function testPostInputType()
    {
        $inputParam = (new PostInputParam('mykey'))->setRequired();
        $this->assertNull($inputParam->getValue());

        $inputParam = (new PostInputParam('mykey'))->setMulti();
        $this->assertNull($inputParam->getValue());

        $_POST['mykey'] = 'asd';
        $inputParam = (new PostInputParam('mykey'))->setRequired();
        $this->assertEquals('asd', $inputParam->getValue());
    }

    public function testFileInputType()
    {
        $_FILES['myfile'] = 'hello';
        $inputParam = (new FileInputParam('myfile'))->setRequired();
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

        $inputParam = (new FileInputParam('myfile'))->setRequired()->setMulti();
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
        $inputParam = (new CookieInputParam('mykey'))->setRequired();
        $this->assertNull($inputParam->getValue());

        $_COOKIE['mykey'] = 'asd';
        $inputParam = (new CookieInputParam('mykey'))->setRequired();
        $this->assertEquals('asd', $inputParam->getValue());
    }

    public function testStaticAvailableValues()
    {
        $_GET['dsgerg'] = 'asfsaf';
        $inputParam = (new GetInputParam('dsgerg'))->setRequired()->setAvailableValues(['vgdgr']);
        $this->assertFalse($inputParam->isValid());

        $_GET['dsgerg'] = 'vgdgr';
        $this->assertTrue($inputParam->isValid());
    }

    public function testRawPostData()
    {
        $inputParam = new RawInputParam('raw_post');
        $this->assertEquals('', $inputParam->getValue());
    }

    public function testPutData()
    {
        $inputParam = new PutInputParam('put');
        $this->assertEquals('', $inputParam->getValue());
    }

    public function testSetMultiOnJsonInputParam()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot use multi json input param');
        (new JsonInputParam('json', '{}'))->setMulti();
    }

    public function testSetMultiOnRawInputParam()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot use multi raw input param');
        (new RawInputParam('raw_post'))->setMulti();
    }
}
