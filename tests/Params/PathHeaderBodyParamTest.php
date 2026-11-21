<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\UnsupportedMediaTypeException;
use Tomaj\NetteApi\Params\BodyInputParam;
use Tomaj\NetteApi\Params\HeaderInputParam;
use Tomaj\NetteApi\Params\PathInputParam;
use Tomaj\NetteApi\Params\PathValues;
use Tomaj\NetteApi\Validation\InputType;
use Tomaj\NetteApi\Params\HeaderBag;

final class PathHeaderBodyParamTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['HTTP_X_REQUEST_ID'], $_SERVER['CONTENT_LENGTH'], $_SERVER['REQUEST_METHOD']);
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
        $this->assertSame('req-7', new HeaderInputParam('X_Request_Id')->getValue(), 'PHP reads "_" in a header name as "-"');
        $this->assertTrue(HeaderBag::has('x_request_id'));

        unset($_SERVER['HTTP_X_REQUEST_ID']);
        $withDefault = new HeaderInputParam('X-Missing')->setDefault('fallback');
        $this->assertSame('fallback', $withDefault->getValue());
    }

    public function testBodyReadsPostAndJson(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['name' => 'Ada'];
        $param = new BodyInputParam('name', InputType::STRING);
        $this->assertSame('Ada', $param->getValue());

        $_POST = [];
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"name":"Grace","extra":1}');
        $this->assertSame('Grace', $param->getValue());

        RequestInput::setRawBody('[1,2]');
        $empty = new BodyInputParam('name')->setDefault('none');
        $this->assertSame('none', $empty->getValue());
        $this->assertSame(['Value must be an object'], $empty->validate()->getErrors());

        unset($_SERVER['REQUEST_METHOD']);
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        RequestInput::setRawBody('{"name":"from-json"}');
        $this->assertSame('none', new BodyInputParam('name')->setDefault('none')->getValue());
    }

    public function testAJsonObjectIsAnArrayForParamsHandlers(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"address":{"city":"Bratislava","tags":[{"a":1}]},"list":[{"b":2}],"name":"Ada"}');

        $this->assertSame(['city' => 'Bratislava', 'tags' => [['a' => 1]]], new BodyInputParam('address', InputType::ARRAY)->getValue());
        $this->assertSame([['b' => 2]], new BodyInputParam('list', InputType::ARRAY)->getValue());
        $this->assertSame('Ada', new BodyInputParam('name')->getValue());
    }

    public function testABodyOverPostMaxSizeIsAnErrorInsteadOfAnEmptyForm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = [];
        $limit = ini_parse_quantity((string) ini_get('post_max_size'));
        if ($limit === 0) {
            $this->markTestSkipped('post_max_size is unlimited');
        }
        $_SERVER['CONTENT_LENGTH'] = (string) ($limit + 1);
        $param = new BodyInputParam('name')->setDefault('none');
        $this->assertSame(['Request body is too large'], $param->validate()->getErrors());

        $_SERVER['CONTENT_LENGTH'] = (string) $limit;
        $this->assertTrue($param->validate()->isOk(), 'an empty form within the limit');
        $this->assertSame('none', $param->getValue());
    }

    public function testMalformedJsonIsAnErrorOfTheBodyParam(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"limit": 5,,}');
        $param = new BodyInputParam('limit')->setDefault('10');
        $this->assertSame(['Syntax error'], $param->validate()->getErrors());

        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=latin1';
        $this->assertSame(['JSON must be UTF-8; the request says charset=latin1'], $param->validate()->getErrors());

        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"limit": 5}');
        $this->assertTrue($param->validate()->isOk());
    }

    public function testAnExplicitNullIsNotReplacedByTheDefault(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"note":null}');
        $this->assertNull(new BodyInputParam('note')->setDefault('fallback')->getValue());
        $this->assertSame(['Field is required'], new BodyInputParam('note')->setRequired()->validate()->getErrors());
    }

    public function testAnUnsupportedMediaTypeIsRejected(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'text/csv';
        RequestInput::setRawBody('a,b');
        $this->expectException(UnsupportedMediaTypeException::class);
        $this->expectExceptionMessage("Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded, multipart/form-data.");
        new BodyInputParam('note')->getValue();
    }

    public function testDeclaredContentTypesAreTheAcceptedOnes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['note' => 'hi'];
        $this->expectException(UnsupportedMediaTypeException::class);
        $this->expectExceptionMessage('Use one of: application/json.');
        new BodyInputParam('note')->setContentTypes(['application/json'])->getValue();
    }

    public function testTheBodyIsReadOncePerRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"a":"1","b":"2"}');
        $reads = new \ReflectionProperty(RequestInput::class, 'plain');
        $a = new BodyInputParam('a');
        $this->assertSame('1', $a->getValue());
        $first = $reads->getValue();
        $this->assertSame('2', new BodyInputParam('b')->getValue());
        $this->assertTrue($a->validate()->isOk());
        $this->assertSame($first, $reads->getValue());

        // the next request through the presenter starts with reset(), a test may change the request without it
        RequestInput::reset();
        $this->assertNull($reads->getValue());
        $this->assertSame('1', $a->getValue(), 'reset() keeps a body given by setRawBody()');
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        RequestInput::setRawBody('{"a":"3"}');
        $this->assertSame('3', $a->getValue());
    }

    public function testAnUnchangedRequestIsNotParsedAgain(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"a":"1"}');
        $param = new BodyInputParam('a');
        $this->assertSame('1', $param->getValue());

        // php://input of a real request does not change, the parsed body is reused
        new \ReflectionProperty(RequestInput::class, 'rawOverride')->setValue(null, '{"a":"2"}');
        $this->assertSame('1', $param->getValue());
    }

    public function testAnOptionalBodyParamAcceptsARequestWithoutABody(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('');
        $param = new BodyInputParam('a');
        $this->assertNull($param->getValue());
        $this->assertTrue($param->validate()->isOk());
    }

    public function testAChangedRequestIsReadAgain(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['a' => '1'];
        $param = new BodyInputParam('a');
        $this->assertSame('1', $param->getValue());
        $_POST = ['a' => '2'];
        $this->assertSame('2', $param->getValue());

        $_POST = [];
        RequestInput::setRawBody('a=3');
        $this->assertNull($param->getValue(), 'PHP parsed a POST form already');
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $this->assertSame('3', $param->getValue());

        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->assertSame(['Syntax error'], $param->validate()->getErrors());
        $this->expectException(UnsupportedMediaTypeException::class);
        $param->setContentTypes(['application/x-www-form-urlencoded'])->getValue();
    }

    public function testBodyReadsAPutFormAndTheContentTypeHeaderLikeTypedHandlers(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        RequestInput::setRawBody('name=Linus');
        $this->assertSame('Linus', new BodyInputParam('name')->getValue());

        unset($_SERVER['CONTENT_TYPE']);
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"name":"Ken"}');
        $this->assertSame('Ken', new BodyInputParam('name')->getValue());
    }

    public function testBodyReadsAnyFieldOfAForm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['name' => 'Ada', 'role' => 'admin'];
        $this->assertSame('admin', new BodyInputParam('role')->getValue());

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_POST = [];
        RequestInput::setRawBody('name=Linus&role=maintainer');
        $this->assertSame('maintainer', new BodyInputParam('role')->getValue());
    }

    public function testAPostFormIsReadFromPostOnly(): void
    {
        // PHP already parsed a POST body into $_POST, an empty one is not parsed again from the raw body
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        RequestInput::setRawBody('name=Linus');
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

    public function testAuthorizationFromAnApacheRewriteIsRead(): void
    {
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer abc';
        $this->assertSame('Bearer abc', HeaderBag::get('Authorization'));

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer direct';
        $this->assertSame('Bearer direct', HeaderBag::get('authorization'));

        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['HTTP_AUTHORIZATION']);
        $this->assertFalse(HeaderBag::has('Authorization'));
    }

    public function testHeadersIgnoreServerInternals(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'abc';
        $_SERVER['HTTP_X_TRACE'] = 'xyz';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->assertSame('abc', HeaderBag::get('x-request-id'));
        $this->assertSame('xyz', HeaderBag::get('X-Trace'));
        $this->assertFalse(HeaderBag::has('t-name'));
        $this->assertSame('application/json', HeaderBag::get('Content-Type'));
    }

    public function testBodyParamReadsJsonObjectsOnly(): void
    {
        RequestInput::setRawBody('{"note":"hi","1":"n"}');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $param = new BodyInputParam('note');
        $this->assertSame('hi', $param->getValue());
        $numeric = new BodyInputParam('1');
        $this->assertSame('n', $numeric->getValue());

        RequestInput::reset();
        RequestInput::setRawBody('[1, 2]');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->assertNull(new BodyInputParam('note')->getValue());

        RequestInput::reset();
        RequestInput::setRawBody('');
        $this->assertNull(new BodyInputParam('note')->getValue());
    }
}
