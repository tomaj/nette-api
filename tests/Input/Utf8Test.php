<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Params\PathValues;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;

final class Utf8Test extends TestCase
{
    protected function setUp(): void
    {
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function invalidUtf8(): \Iterator
    {
        yield 'latin 1 letter' => ["\xE9"];
        yield 'lone continuation byte' => ["a\x80b"];
        yield 'truncated sequence' => ["abc\xE2\x82"];
        yield 'overlong slash' => ["\xC0\xAF"];
        yield 'surrogate' => ["\xED\xA0\x80"];
        yield 'beyond the last code point' => ["\xF4\x90\x80\x80"];
    }

    #[DataProvider('invalidUtf8')]
    public function testInvalidUtf8InAQueryStringIsRejected(string $value): void
    {
        $_GET = ['q' => $value];

        $this->assertSame(['q' => ['Value must be valid UTF-8']], $this->errors(new Utf8Handler()));
    }

    public function testInvalidUtf8InAHeaderIsRejected(): void
    {
        $_SERVER['HTTP_X_T'] = "\xE9";

        $this->assertSame(['X-T' => ['Value must be valid UTF-8']], $this->errors(new Utf8Handler()));
    }

    public function testInvalidUtf8InAPathValueIsRejected(): void
    {
        PathValues::set(['id' => "\xE9"]);

        $this->assertSame(['id' => ['Value must be valid UTF-8']], $this->errors(new Utf8PathHandler()));
    }

    public function testInvalidUtf8InAFormValueIsRejected(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => "\xE9"];
        RequestInput::setRawBody('name=%E9');

        $this->assertSame(['name' => ['Value must be valid UTF-8']], $this->errors(new Utf8FormHandler()));
    }

    public function testInvalidUtf8InAnItemOfAListIsRejectedWithItsPosition(): void
    {
        $_GET = ['tags' => ['ok', "\xE9"]];

        $this->assertSame(['tags[1]' => ['Value must be valid UTF-8']], $this->errors(new Utf8ListHandler()));
    }

    public function testInvalidUtf8IsReportedInsteadOfAMisleadingLengthOrPatternMessage(): void
    {
        $_GET = ['short' => "\xE9\xE9\xE9", 'u' => "\xE9"];

        $this->assertSame([
            'short' => ['Value must be valid UTF-8'],
            'u' => ['Value must be valid UTF-8'],
        ], $this->errors(new Utf8LengthHandler()));
    }

    public function testInvalidUtf8ForAStringBackedEnumIsAnUnknownCase(): void
    {
        $_GET = ['kind' => "\xE9"];

        $this->assertSame(['kind' => ['Value must be one of: a, b']], $this->errors(new Utf8EnumHandler()));
    }

    public function testValidUtf8IsAccepted(): void
    {
        $_GET = ['q' => 'žluťoučký kůň 😀', 'short' => '', 'u' => 'é'];
        $_SERVER['HTTP_X_T'] = 'ž';
        PathValues::set(['id' => 'é']);

        $this->assertFalse(new ParamsProcessor(new Utf8Handler()->params())->isError());
        $this->assertFalse(new ParamsProcessor(new Utf8PathHandler()->params())->isError());
        $this->assertFalse(new ParamsProcessor(new Utf8LengthHandler()->params())->isError());
    }

    public function testAJsonStringWithSomethingThatLooksLikeInvalidUtf8EscapesIsFine(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('{"name":"\u00e9"}');

        $this->assertFalse(new ParamsProcessor(new Utf8FormHandler()->params())->isError());
    }

    public function testAHandlerWithParamsKeepsAcceptingAnyBytes(): void
    {
        $_GET = ['q' => "\xE9"];
        $handler = new class extends BaseHandler {
            public function params(): array
            {
                return [new GetInputParam('q')];
            }

            public function handle(array $params): ResponseInterface
            {
                return new JsonApiResponse(200, []);
            }
        };

        $this->assertFalse(new ParamsProcessor($handler->params())->isError());
    }

    private function clean(): void
    {
        RequestInput::reset();
        PathValues::reset();
        $_GET = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_T'], $_SERVER['CONTENT_TYPE'], $_SERVER['REQUEST_METHOD']);
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(TypedHandler $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertTrue($processor->isError());

        return $processor->getErrors();
    }
}

final readonly class Utf8Input
{
    public function __construct(
        #[Query] public string $q = 'd',
        #[Header('X-T')] public string $h = 'd',
    ) {
    }
}

final class Utf8Handler extends TypedHandler
{
    public function handleDto(Utf8Input $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class Utf8PathInput
{
    public function __construct(#[Path] public string $id)
    {
    }
}

final class Utf8PathHandler extends TypedHandler
{
    public function handleDto(Utf8PathInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class Utf8FormInput
{
    public function __construct(#[Body] public string $name)
    {
    }
}

final class Utf8FormHandler extends TypedHandler
{
    public function handleDto(Utf8FormInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class Utf8ListInput
{
    /**
     * @param list<string> $tags
     */
    public function __construct(#[Query] public array $tags = [])
    {
    }
}

final class Utf8ListHandler extends TypedHandler
{
    public function handleDto(Utf8ListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class Utf8LengthInput
{
    public function __construct(
        #[Query] #[Length(max: 2)] public string $short = 'x',
        #[Query] #[Pattern('/^.+$/u')] public string $u = 'a',
    ) {
    }
}

final class Utf8LengthHandler extends TypedHandler
{
    public function handleDto(Utf8LengthInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

enum Utf8Kind: string
{
    case A = 'a';
    case B = 'b';
}

final readonly class Utf8EnumInput
{
    public function __construct(#[Query] public Utf8Kind $kind)
    {
    }
}

final class Utf8EnumHandler extends TypedHandler
{
    public function handleDto(Utf8EnumInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
