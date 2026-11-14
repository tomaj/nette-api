<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Params\BodyInputParam;
use Tomaj\NetteApi\Params\HeaderInputParam;
use Tomaj\NetteApi\Params\PathInputParam;
use Tomaj\NetteApi\Params\PathValues;
use Tomaj\NetteApi\Validation\InputType;

final class PathHeaderBodyParamTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_X_REQUEST_ID'], $_SERVER['CONTENT_LENGTH']);
        PathValues::reset();
        RequestInput::reset();
    }

    public function testPathIsRequiredAndConvertsIntegers(): void
    {
        $param = new PathInputParam('id', InputType::INTEGER);
        $this->assertTrue($param->isRequired());
        $this->assertSame(['Field is required'], $param->validate()->getErrors());

        PathValues::set(['id' => '12']);
        $this->assertTrue($param->validate()->isOk());
        $this->assertSame(12, $param->getValue());
    }

    public function testHeaderIsCaseInsensitive(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'req-7';
        $param = new HeaderInputParam('x-request-id', InputType::STRING);
        $this->assertFalse($param->isRequired());
        $this->assertSame('req-7', $param->getValue());
        $this->assertTrue($param->validate()->isOk());

        unset($_SERVER['HTTP_X_REQUEST_ID']);
        $withDefault = new HeaderInputParam('X-Missing')->setDefault('fallback');
        $this->assertSame('fallback', $withDefault->getValue());
    }

    public function testBodyReadsPostAndJson(): void
    {
        $_POST = ['name' => 'Ada'];
        $param = new BodyInputParam('name', InputType::STRING);
        $this->assertSame('Ada', $param->getValue());

        $_POST = [];
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"name":"Grace","extra":1}');
        $this->assertSame('Grace', $param->getValue());

        RequestInput::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('[1,2]');
        $empty = new BodyInputParam('name')->setDefault('none');
        $this->assertSame('none', $empty->getValue());

        RequestInput::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = [];
        RequestInput::setRawBody('{"name":"from-json"}');
        $this->assertSame('none', new BodyInputParam('name')->setDefault('none')->getValue());
    }

    public function testSchemaAndContentTypesRoundTrip(): void
    {
        $param = new BodyInputParam('name')
            ->setOpenApiSchema(['type' => 'string'])
            ->setContentTypes(['application/json', 'application/x-www-form-urlencoded']);
        $this->assertSame(['type' => 'string'], $param->getOpenApiSchema());
        $this->assertSame(['application/json', 'application/x-www-form-urlencoded'], $param->getContentTypes());
        $this->assertNull(new BodyInputParam('other')->getOpenApiSchema());
        $this->assertSame([], new BodyInputParam('other')->getContentTypes());
    }
}
