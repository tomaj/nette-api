<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\RequestInput;
use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Consumes;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\UnsupportedMediaTypeException;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\FormHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\FormInput;
use Tomaj\NetteApi\Test\Input\Fixtures\BodyFieldHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\BodyFieldInput;

final class BodyParsingTest extends TestCase
{
    protected function setUp(): void
    {
        RequestInput::reset();
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        RequestInput::reset();
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['REQUEST_METHOD']);
    }

    public function testABodyWithoutAContentTypeIsNotIgnoredWhenAllFieldsAreOptional(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('{"a":"x"}');

        $this->expectException(UnsupportedMediaTypeException::class);
        $this->expectExceptionMessage('Content-Type is missing');
        new ParamsProcessor(new OptionalHandler()->params())->isError();
    }

    public function testAnEmptyRequestWithoutAContentTypeStillSucceedsWhenAllFieldsAreOptional(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('');

        $this->assertFalse(new ParamsProcessor(new OptionalHandler()->params())->isError());
    }

    public function testMultipartWithPutAnswersWithTheReasonInsteadOfMissingFields(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        RequestInput::setRawBody("--x\r\nContent-Disposition: form-data; name=\"a\"\r\n\r\nv\r\n--x--\r\n");

        $processor = new ParamsProcessor(new MultipartHandler()->params());
        $this->assertTrue($processor->isError());
        $this->assertSame(
            ['body' => ['multipart/form-data is only parsed by PHP for POST requests, but this one is PUT. Send it with POST, or use application/json']],
            $processor->getErrors(),
        );
    }

    public function testAnEmptyMultipartPutIsNotAnError(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        RequestInput::setRawBody('');

        $this->assertFalse(new ParamsProcessor(new MultipartHandler()->params())->isError());
    }

    public function testTooManyFormFieldsDoNotReachTheErrorHandlerOfTheApplication(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        RequestInput::setRawBody(implode('&', array_map(static fn (int $i): string => 'f' . $i . '=1', range(1, $limit + 1))));

        $seen = [];
        set_error_handler(static function (int $level, string $message) use (&$seen): bool {
            $seen[] = $message;

            return true;
        });
        error_clear_last();
        try {
            $this->assertTrue(new ParamsProcessor(new FormHandler()->params())->isError());
            $afterwards = $seen;
            // the handler of the application is back in place: it receives the next warning
            hex2bin('abc');
        } finally {
            restore_error_handler();
        }

        // PHP's own handler runs only when ours declines the warning, and it would leave it in error_get_last()
        $this->assertNull(error_get_last());
        $this->assertSame([], $afterwards);
        $this->assertCount(1, $seen);
    }

    public function testTooManyFormFieldsAreAnErrorInsteadOfAWarning(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        RequestInput::setRawBody(implode('&', array_map(static fn (int $i): string => 'f' . $i . '=1', range(1, $limit + 1))));

        $processor = new ParamsProcessor(new FormHandler()->params());
        $this->assertTrue($processor->isError());
        $this->assertSame(['body' => ['Too many form fields']], $processor->getErrors());
    }

    public function testExactlyTheLimitOfFormFieldsIsAccepted(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        RequestInput::setRawBody(implode('&', array_map(static fn (int $i): string => 'f' . $i . '=1', range(1, $limit))));

        $this->assertFalse(new ParamsProcessor(new FormHandler()->params())->isError());
    }

    public function testAPostFormWithMoreFieldsThanMaxInputVarsIsRejected(): void
    {
        $limit = (int) ini_get('max_input_vars');
        // PHP keeps the first `limit` variables and drops the rest without a word
        $_POST = array_fill_keys(array_map(static fn (int $i): string => 'f' . $i, range(1, $limit)), '1');

        $errors = $this->post('application/x-www-form-urlencoded', self::pairs('f', $limit + 1), new BodyFieldHandler());

        $this->assertSame(['body' => ['Too many form fields']], $errors);
    }

    public function testAPostFormWithExactlyTheLimitIsAccepted(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_POST = array_fill_keys(array_map(static fn (int $i): string => 'f' . $i, range(1, $limit)), '1');

        $this->assertNull($this->post('application/x-www-form-urlencoded', self::pairs('f', $limit), new BodyFieldHandler()));
    }

    public function testAPostFormIsCountedWithItsNestedValues(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_POST = ['a' => array_fill(0, $limit, '1')];
        $raw = implode('&', array_map(static fn (int $i): string => 'a[]=' . $i, range(1, $limit + 1)));

        $this->assertSame(['body' => ['Too many form fields']], $this->post('application/x-www-form-urlencoded', $raw, new BodyFieldHandler()));
    }

    public function testASmallPostFormIsNotParsedAgain(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_POST = ['a' => 'x'];

        // the body is not even looked at: PHP cannot have cut a form that fits
        $this->assertNull($this->post('application/x-www-form-urlencoded', self::pairs('f', $limit + 1), new BodyFieldHandler()));
    }

    public function testAMultipartPostThatReachesTheLimitIsTakenAsCut(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_POST = array_fill_keys(array_map(static fn (int $i): string => 'f' . $i, range(1, $limit)), '1');

        // PHP does not give a multipart body back to count it again, so a form at the limit is taken as one PHP cut
        $this->assertSame(['body' => ['Too many form fields']], $this->post('multipart/form-data; boundary=x', '', new BodyFieldMultipartHandler()));
    }

    public function testAMultipartPostUnderTheLimitIsAccepted(): void
    {
        $limit = (int) ini_get('max_input_vars');
        // max_input_vars counts the values, not the arrays that hold them
        $_POST = ['a' => 'x', 'list' => array_fill(0, $limit - 2, '1')];

        $this->assertNull($this->post('multipart/form-data; boundary=x', '', new BodyFieldMultipartHandler()));
    }

    public function testARequestWithoutABodyIsNotRejectedForItsContentType(): void
    {
        $this->assertNull($this->post('text/plain', '', new OptionalHandler()));
    }

    public function testABodyInAnotherMediaTypeIsRejected(): void
    {
        foreach ([
            'raw body' => static fn () => RequestInput::setRawBody('x'),
            'form fields' => static function (): void {
                $_POST = ['a' => 'x'];
            },
            'files' => static function (): void {
                $_FILES = ['a' => ['name' => 'a.txt', 'error' => UPLOAD_ERR_OK]];
            },
        ] as $case => $fill) {
            [$_POST, $_FILES] = [[], []];
            RequestInput::setRawBody('');
            $_SERVER['CONTENT_TYPE'] = 'text/plain';
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $fill();
            try {
                new ParamsProcessor(new OptionalHandler()->params())->isError();
                $this->fail($case . ' was accepted');
            } catch (UnsupportedMediaTypeException $exception) {
                $this->assertStringContainsString("Content-Type 'text/plain' is not supported", $exception->getMessage(), $case);
            }
        }
    }

    public function testARequiredBodyInAnotherMediaTypeIsRejectedEvenWhenEmpty(): void
    {
        $this->expectException(UnsupportedMediaTypeException::class);
        $this->post('text/plain', '', new CreateOrderHandler());
    }

    public function testAJsonCharsetOtherThanUtf8IsRejected(): void
    {
        foreach (['application/json; charset=ISO-8859-1' => 'iso-8859-1', 'application/json;charset="latin1"' => 'latin1', 'application/json; CHARSET=latin1' => 'latin1'] as $contentType => $charset) {
            RequestInput::reset();
            $_SERVER['CONTENT_TYPE'] = $contentType;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            RequestInput::setRawBody('{"a":"x"}');

            $processor = new ParamsProcessor(new FormHandler()->params());
            $this->assertTrue($processor->isError(), $contentType);
            $this->assertSame(['JSON must be UTF-8; the request says charset=' . $charset], $processor->getErrors()['body']);
        }

        foreach (['application/json; charset=UTF-8', 'application/json; Charset="utf-8"', 'application/json'] as $contentType) {
            RequestInput::reset();
            $_SERVER['CONTENT_TYPE'] = $contentType;
            RequestInput::setRawBody('{"a":"x"}');
            $this->assertFalse(new ParamsProcessor(new FormHandler()->params())->isError(), $contentType);
        }
    }

    #[DataProvider('charsets')]
    public function testJsonCharsets(string $contentType, ?string $rejected): void
    {
        $errors = $this->post($contentType, '{"a":"x"}', new BodyFieldHandler());

        if ($rejected === null) {
            $this->assertNull($errors, $contentType);
        } else {
            $this->assertSame(['body' => ['JSON must be UTF-8; the request says charset=' . $rejected]], $errors, $contentType);
        }
    }

    /**
     * @return \Iterator<string, array{string, string|null}>
     */
    public static function charsets(): \Iterator
    {
        yield 'utf-8' => ['application/json; charset=utf-8', null];
        yield 'utf8 spelling' => ['application/json; charset=utf8', null];
        yield 'UTF8 upper case' => ['application/json; charset=UTF8', null];
        yield 'us-ascii' => ['application/json; charset=US-ASCII', null];
        yield 'quoted' => ['application/json; charset="utf-8"', null];
        yield 'spaces around the equals sign' => ['application/json; charset = utf-8', null];
        yield 'trailing space' => ['application/json; charset="UTF-8" ', null];
        yield 'quoted value that only looks like a charset' => ['application/json; foo="a;charset=latin1"', null];
        yield 'the first charset wins' => ['application/json; charset=utf-8; charset=latin1', null];
        yield 'another parameter first' => ['application/json; foo=1; charset=latin1', 'latin1'];
        yield 'latin1' => ['application/json; charset=ISO-8859-1', 'iso-8859-1'];
        yield 'quoted latin1' => ['application/json;charset="latin1"', 'latin1'];
        yield 'long value is cut' => ['application/json; charset=' . str_repeat('X', 100), str_repeat('x', 30)];
        yield 'value of exactly the shown length' => ['application/json; charset=' . str_repeat('y', 30), str_repeat('y', 30)];
    }

    private static function pairs(string $name, int $count): string
    {
        return implode('&', array_map(static fn (int $i): string => $name . $i . '=1', range(1, $count)));
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function post(string $contentType, string $raw, TypedHandler $handler): ?array
    {
        $_SERVER['CONTENT_TYPE'] = $contentType;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody($raw);
        $processor = new ParamsProcessor($handler->params());

        return $processor->isError() ? $processor->getErrors() : null;
    }

}

final readonly class OptionalInput
{
    public function __construct(
        #[Body] public ?string $a = null,
        #[Body] public ?Color $c = null,
    ) {
    }
}

final class OptionalHandler extends TypedHandler
{
    public function handleDto(OptionalInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[Consumes(MediaTypes::MULTIPART)]
final class MultipartHandler extends TypedHandler
{
    public function handleDto(FormInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

enum Color: string
{
    case Red = 'red';
}

#[Consumes(MediaTypes::MULTIPART)]
final class BodyFieldMultipartHandler extends TypedHandler
{
    public function handleDto(BodyFieldInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
