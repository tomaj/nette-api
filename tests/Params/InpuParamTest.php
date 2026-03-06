<?php

declare(strict_types=1);

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
        self::assertTrue($inputParam->validate()->isOk());
        self::assertEquals('hello', $inputParam->getValue());
        unset($_POST['mykey1']);

        $inputParam = new PostInputParam('mykey2');
        self::assertTrue($inputParam->validate()->isOk());
        self::assertNull($inputParam->getValue());

        $inputParam = (new PostInputParam('mykey3'))->setRequired()->setAvailableValues(['a', 'b']);
        $_POST['mykey3'] = 'hello';
        self::assertFalse($inputParam->validate()->isOk());
        self::assertEquals('hello', $inputParam->getValue());
        $_POST['mykey3'] = 'a';
        self::assertTrue($inputParam->validate()->isOk());
        self::assertEquals('a', $inputParam->getValue());
        unset($_POST['mykey3']);

        $inputParam = new GetInputParam('asdsd');
        self::assertNull($inputParam->getValue());

        $inputParam = new JsonInputParam('json', '{}');
        self::assertTrue($inputParam->validate()->isOk());
        self::assertNull($inputParam->getValue());
        self::assertEquals([], $inputParam->validate()->getErrors());

        $inputParam = (new JsonInputParam('json', '{}'))->setRequired();
        self::assertFalse($inputParam->validate()->isOk());
        self::assertNull($inputParam->getValue());
        self::assertEquals(['missing data'], $inputParam->validate()->getErrors());

        $inputParam = (new JsonInputParam('json', '{"type": "object"}'))->setDefault('{}');
        self::assertTrue($inputParam->validate()->isOk());
        self::assertEquals([], $inputParam->getValue());
        self::assertEquals([], $inputParam->validate()->getErrors());

        $inputParam = (new JsonInputParam('json', '{"type": "string"}'))->setDefault('{"hello": "world"}');
        self::assertFalse($inputParam->validate()->isOk());
        self::assertEquals(['hello' => 'world'], $inputParam->getValue());
        self::assertEquals(['Object value found, but a string is required'], $inputParam->validate()->getErrors());
    }

    public function testVariableAccess()
    {
        $inputParam = (new GetInputParam('mykey4'))->setRequired()->setAvailableValues(['c', 'asdsadsad']);

        self::assertEquals('GET', $inputParam->getType());
        self::assertEquals('mykey4', $inputParam->getKey());
        self::assertEquals(true, $inputParam->isRequired());
        self::assertEquals(['c' => 'c', 'asdsadsad' => 'asdsadsad'], $inputParam->getAvailableValues());
    }

    public function testNotFoundFileType()
    {
        $inputParam = (new FileInputParam('myfile'))->setRequired();
        self::assertNull($inputParam->getValue());
        self::assertFalse($inputParam->validate()->isOk());
    }

    public function testGetInputType()
    {
        $inputParam = (new GetInputParam('mykey'))->setRequired();
        self::assertNull($inputParam->getValue());
        self::assertEquals('', $inputParam->getDescription());

        $inputParam = (new GetInputParam('mykey'))->setMulti()->setDescription('mykey description');
        self::assertNull($inputParam->getValue());
        self::assertEquals('mykey description', $inputParam->getDescription());

        $_GET['mykey'] = 'asd';
        $inputParam = (new GetInputParam('mykey'))->setRequired();
        self::assertEquals('asd', $inputParam->getValue());
    }

    public function testPostInputType()
    {
        $inputParam = (new PostInputParam('mykey'))->setRequired();
        self::assertNull($inputParam->getValue());

        $inputParam = (new PostInputParam('mykey'))->setMulti();
        self::assertNull($inputParam->getValue());

        $_POST['mykey'] = 'asd';
        $inputParam = (new PostInputParam('mykey'))->setRequired();
        self::assertEquals('asd', $inputParam->getValue());
    }

    public function testFileInputType()
    {
        $_FILES['myfile'] = 'hello';
        $inputParam = (new FileInputParam('myfile'))->setRequired();
        self::assertEquals('hello', $inputParam->getValue());
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

        self::assertCount(2, $value);

        self::assertEquals('file1', $value[0]['name']);
        self::assertEquals('text/plain', $value[0]['type']);
        self::assertEquals('/tmp/1', $value[0]['tmp_name']);
        self::assertEquals(101, $value[0]['size']);

        self::assertEquals('file2', $value[1]['name']);
        self::assertEquals('image/jpeg', $value[1]['type']);
        self::assertEquals('/tmp/2', $value[1]['tmp_name']);
        self::assertEquals(102, $value[1]['size']);
    }

    public function testCookiesValues()
    {
        $inputParam = (new CookieInputParam('mykey'))->setRequired();
        self::assertNull($inputParam->getValue());

        $_COOKIE['mykey'] = 'asd';
        $inputParam = (new CookieInputParam('mykey'))->setRequired();
        self::assertEquals('asd', $inputParam->getValue());
    }

    public function testStaticAvailableValues()
    {
        $_GET['dsgerg'] = 'asfsaf';
        $inputParam = (new GetInputParam('dsgerg'))->setRequired()->setAvailableValues(['vgdgr']);
        self::assertFalse($inputParam->validate()->isOk());

        $_GET['dsgerg'] = 'vgdgr';
        self::assertTrue($inputParam->validate()->isOk());
    }

    public function testStaticAvailableValuesWithSpecialKeys()
    {
        $_GET['dsgerg'] = 'asfsaf';
        $inputParam = (new GetInputParam('dsgerg'))->setRequired()->setAvailableValues(['vgdgr' => 'VGDGR']);
        self::assertFalse($inputParam->validate()->isOk());

        $_GET['dsgerg'] = 'VGDGR';
        self::assertFalse($inputParam->validate()->isOk());

        $_GET['dsgerg'] = 'vgdgr';
        self::assertTrue($inputParam->validate()->isOk());
    }

    public function testRawPostData()
    {
        $inputParam = new RawInputParam('raw_post');
        self::assertEquals('', $inputParam->getValue());
    }

    public function testPutData()
    {
        $inputParam = new PutInputParam('put');
        self::assertEquals('', $inputParam->getValue());
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
