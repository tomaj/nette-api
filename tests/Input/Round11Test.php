<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\Field;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\Consumes;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Input\UnsupportedMediaTypeException;
use Tomaj\NetteApi\OpenApi\ResponseBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Params\HeaderBag;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

final class Round11Test extends TestCase
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
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['REQUEST_METHOD'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['HTTP_AUTHORIZATION']);
    }

    /**
     * @return \Iterator<string, array{class-string, string}>
     */
    public static function invalidHandlers(): \Iterator
    {
        yield 'nested dto in query' => [R11QueryDtoHandler::class, 'reads #[Query], which carries flat values'];
        yield 'list of dto in query' => [R11QueryListHandler::class, 'cannot be list<'];
        yield 'object default (date)' => [R11DateDefaultHandler::class, 'has a default of type DateTimeImmutable'];
        yield 'object default (dto)' => [R11DtoDefaultHandler::class, 'has a default of type Tomaj\\NetteApi\\Test\\Input\\R11Inner'];
        yield 'object default inside a list' => [R11ListObjectDefaultHandler::class, 'has a default of type'];
        yield 'list of nullable items' => [R11NullableItemsHandler::class, 'one non-nullable type'];
        yield 'list of unions' => [R11UnionItemsHandler::class, 'one non-nullable type'];
        yield 'list of lists' => [R11NestedListsHandler::class, 'one non-nullable type'];
        yield 'associative array' => [R11AssocHandler::class, 'not a list'];
        yield 'unreadable doc type' => [R11OtherDocHandler::class, 'Found `iterable<string>`'];
        yield 'DateTime' => [R11PlainDateTimeHandler::class, 'Use DateTimeImmutable'];
        yield 'DateTimeInterface' => [R11DateInterfaceHandler::class, 'Use DateTimeImmutable'];
        yield 'empty split separator' => [R11EmptySplitHandler::class, 'Its constructor is __construct(string $separator)'];
        yield 'split into ints' => [R11SplitIntsHandler::class, 'Split gives list<string>'];
        yield 'list with a trailing union' => [R11TrailingUnionHandler::class, 'Found `list<int>|string`'];
        yield 'array of arrays' => [R11ArrayOfArraysHandler::class, 'not a list'];
        yield 'brackets with a trailing union' => [R11BracketUnionHandler::class, 'Found `string[]|int`'];
        yield 'array of lists' => [R11ArrayOfListsHandler::class, 'not a list'];
        yield 'union before brackets' => [R11UnionBracketsHandler::class, 'Found `int|string[]`'];
        yield 'generic of array' => [R11GenericOfArrayHandler::class, 'Found `Collection<array>`'];
        yield 'caster constructor with typed and untyped parameters' => [R11BadCasterHandler::class, 'Its constructor is __construct(string $format, $loose)'];
        yield 'caster without a constructor' => [R11NoCtorCasterHandler::class, 'Its constructor is __construct()'];
        yield 'dash in a path name' => [R11PathDashHandler::class, 'can only have letters, digits and underscores'];
        yield 'underscore in a header name' => [R11HeaderUnderscoreHandler::class, "Use #[Header('X-Foo')]"];
    }

    /**
     * @param class-string $handler
     */
    #[DataProvider('invalidHandlers')]
    public function testCompileTimeMessage(string $handler, string $needle): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($needle);
        DtoCompiler::compile($handler);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body): void
    {
        RequestInput::reset();
        RequestInput::setRawBody((string) json_encode($body));
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
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

    public function testPathNameMessageSuggestsTheCorrectedName(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Use for example #[Path('a_b')].");
        DtoCompiler::compile(R11PathDashHandler::class);
    }

    public function testSplitSeparatorMessageHasNoDoublePeriod(): void
    {
        try {
            DtoCompiler::compile(R11EmptySplitHandler::class);
            $this->fail('compiled');
        } catch (\LogicException $exception) {
            $this->assertStringNotContainsString('..', $exception->getMessage());
            $this->assertStringContainsString("#[Cast(Split::class, ',')]. Its constructor", $exception->getMessage());
            $this->assertStringNotContainsString('d.m.Y', $exception->getMessage());
        }
    }

    public function testAnUntypedParamTagFallsBackToTheVarOfTheProperty(): void
    {
        $this->assertSame('int', DtoCompiler::compile(R11VarFallbackHandler::class)->fields[0]->listItem);
    }

    public function testEveryCommonPhpDocSpellingOfAListIsRead(): void
    {
        $fields = DtoCompiler::compile(R11DocFormsHandler::class)->fields;

        $this->assertSame(
            ['string', 'string', 'string', 'string', 'string', 'string', 'int', 'float'],
            array_map(static fn (Field $field): string => (string) $field->listItem, $fields),
        );
    }

    public function testABodyWithoutAContentTypeIsNotIgnoredWhenAllFieldsAreOptional(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('{"a":"x"}');

        $this->expectException(UnsupportedMediaTypeException::class);
        $this->expectExceptionMessage('Content-Type is missing');
        new ParamsProcessor(new R11OptionalHandler()->params())->isError();
    }

    public function testAnEmptyRequestWithoutAContentTypeStillSucceedsWhenAllFieldsAreOptional(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('');

        $this->assertFalse(new ParamsProcessor(new R11OptionalHandler()->params())->isError());
    }

    public function testMultipartWithPutAnswersWithTheReasonInsteadOfMissingFields(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        RequestInput::setRawBody("--x\r\nContent-Disposition: form-data; name=\"a\"\r\n\r\nv\r\n--x--\r\n");

        $processor = new ParamsProcessor(new R11MultipartHandler()->params());
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

        $this->assertFalse(new ParamsProcessor(new R11MultipartHandler()->params())->isError());
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
            $this->assertTrue(new ParamsProcessor(new R11FormHandler()->params())->isError());
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

        $processor = new ParamsProcessor(new R11FormHandler()->params());
        $this->assertTrue($processor->isError());
        $this->assertSame(['body' => ['Too many form fields']], $processor->getErrors());
    }

    public function testExactlyTheLimitOfFormFieldsIsAccepted(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        RequestInput::setRawBody(implode('&', array_map(static fn (int $i): string => 'f' . $i . '=1', range(1, $limit))));

        $this->assertFalse(new ParamsProcessor(new R11FormHandler()->params())->isError());
    }

    public function testAJsonCharsetOtherThanUtf8IsRejected(): void
    {
        foreach (['application/json; charset=ISO-8859-1' => 'iso-8859-1', 'application/json;charset="latin1"' => 'latin1', 'application/json; CHARSET=latin1' => 'latin1'] as $contentType => $charset) {
            RequestInput::reset();
            $_SERVER['CONTENT_TYPE'] = $contentType;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            RequestInput::setRawBody('{"a":"x"}');

            $processor = new ParamsProcessor(new R11FormHandler()->params());
            $this->assertTrue($processor->isError(), $contentType);
            $this->assertSame(['JSON must be UTF-8; the request says charset=' . $charset], $processor->getErrors()['body']);
        }

        foreach (['application/json; charset=UTF-8', 'application/json; Charset="utf-8"', 'application/json'] as $contentType) {
            RequestInput::reset();
            $_SERVER['CONTENT_TYPE'] = $contentType;
            RequestInput::setRawBody('{"a":"x"}');
            $this->assertFalse(new ParamsProcessor(new R11FormHandler()->params())->isError(), $contentType);
        }
    }

    public function testTheBudgetStopsInsideAnObjectToo(): void
    {
        $this->json(['ids' => array_fill(0, 99, 'x'), 'inner' => new \stdClass()]);
        $errors = $this->errors(new R11BudgetObjectHandler());

        $this->assertSame(['Field is required'], $errors['inner.a']);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body']);
        $this->assertArrayNotHasKey('inner', $errors);
        $this->assertArrayNotHasKey('inner.b', $errors);
        $this->assertArrayNotHasKey('inner.c', $errors);
    }

    public function testTheBudgetEndsAtTheTopLevelToo(): void
    {
        $this->json(['ids' => array_fill(0, 99, 'x'), 'x' => 'a', 'y' => 'b']);
        $errors = $this->errors(new R11BudgetTopHandler());

        $this->assertSame(['Value must be an integer'], $errors['x']);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body']);
        $this->assertArrayNotHasKey('y', $errors);
    }

    public function testConstraintMessagesCountTowardsTheBudget(): void
    {
        $this->json(['items' => array_fill(0, 150, ['name' => 'too long'])]);
        $errors = $this->errors(new R11ConstraintBudgetHandler());

        $this->assertCount(101, $errors);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body']);
        $this->assertArrayHasKey('items[99].name', $errors);
        $this->assertArrayNotHasKey('items[100].name', $errors);
    }

    public function testLengthCountsUtf8CharactersWhateverTheInternalEncoding(): void
    {
        $encoding = mb_internal_encoding();
        mb_internal_encoding('ISO-8859-1');
        try {
            $this->assertNull(new Length(max: 3)->validate('čšž', new ConstraintContext('x', self::class, [], [])));
        } finally {
            mb_internal_encoding($encoding);
        }
    }

    public function testASmallRangeBoundIsNotPrintedAsZero(): void
    {
        $context = new ConstraintContext('x', self::class, [], []);
        $this->assertSame('Value must be at most 1.0E-12', new Range(max: 1.0E-12)->validate(1.0, $context));
        $this->assertSame('Value must be at least -2.0E-11', new Range(min: -2.0E-11)->validate(-1.0, $context));
        $this->assertSame('Value must be at most 0.5', new Range(max: 0.5)->validate(1.0, $context));
        $this->assertSame('Value must be at least 0', new Range(min: 0)->validate(-1, $context));
        $this->assertSame('Value must be at most 0', new Range(max: 0.0)->validate(1, $context));
        $this->assertSame('Value must be at most 100', new Range(max: 100.0)->validate(101, $context));
        $this->assertSame('Value must be at most 9223372036854775806', new Range(max: PHP_INT_MAX - 1)->validate(PHP_INT_MAX, $context));
    }

    public function testRfc3339AcceptsLowercaseSeparators(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        foreach (['2024-05-01t10:00:00z' => '2024-05-01T10:00:00+00:00', '2024-05-01t10:00:00.5+02:00' => '2024-05-01T10:00:00+02:00'] as $input => $expected) {
            RequestInput::reset();
            RequestInput::setRawBody((string) json_encode(['at' => $input]));
            $processor = new ParamsProcessor(new R11DateHandler()->params());
            $this->assertFalse($processor->isError(), $input);
            $at = $processor->getValues()['at'];
            $this->assertInstanceOf(\DateTimeImmutable::class, $at);
            $this->assertSame($expected, $at->format('c'));
        }

        RequestInput::reset();
        RequestInput::setRawBody('{"at":"2016-12-31T23:59:60Z"}');
        $this->assertTrue(new ParamsProcessor(new R11DateHandler()->params())->isError());
    }

    /**
     * @return \Iterator<string, array{string, ?string}>
     */
    public static function patterns(): \Iterator
    {
        yield 'plain' => ['/^[a-z]+$/', '^[a-z]+$'];
        yield 'escaped plus' => ['/^a\+\+$/', '^a\+\+$'];
        yield 'lookahead and named group' => ['/^(?=a)(?<n>a|b)+x$/u', '^(?=a)(?<n>a|b)+x$'];
        yield 'start anchor' => ['/\Aab\z/', null];
        yield 'end anchor Z' => ['/ab\Z/', null];
        yield 'possessive' => ['/^a++$/', null];
        yield 'possessive braces' => ['/^a{2}+$/', null];
        yield 'possessive question' => ['/^a?+$/', null];
        yield 'atomic group' => ['/^(?>a+)b$/', null];
        yield 'python group' => ['/(?P<n>a)/', null];
        yield 'inline flag' => ['/(?i)a/', null];
        yield 'scoped flag' => ['/(?i:a)b/', null];
        yield 'horizontal space' => ['/a\hb/', null];
    }

    #[DataProvider('patterns')]
    public function testOnlyEcmaCompatiblePatternsReachOpenApi(string $pattern, ?string $expected): void
    {
        $this->assertSame($expected, new Pattern($pattern)->expression());
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

    public function testTheUnsupportedMediaTypeResponseDescribesItsDetail(): void
    {
        $legacy = new ResponseBuilder()->build(new R11FormHandler(), new NoAuthorization(), true, new SchemaRegistry());
        $schema = $legacy[415]['content']['application/json; charset=utf-8']['schema'];
        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['error']],
                'message' => ['type' => 'string'],
                'detail' => ['type' => 'string', 'description' => 'Which media types the endpoint reads. Sent only when error details are on.'],
            ],
            'required' => ['status', 'message'],
        ], $schema);
        $this->assertSame('Unsupported media type', $legacy[415]['description']);

        $problem = new ResponseBuilder(true)->build(new R11FormHandler(), new NoAuthorization(), true, new SchemaRegistry());
        $this->assertSame(['$ref' => '#/components/schemas/ProblemDetails'], $problem[415]['content']['application/problem+json; charset=utf-8']['schema']);
    }
}

final readonly class R11Inner
{
    public function __construct(#[Body] public string $q = 'x')
    {
    }
}

final readonly class R11QueryDtoInput
{
    public function __construct(
        #[Query] public R11Inner $x,
    ) {
    }
}

final class R11QueryDtoHandler extends TypedHandler
{
    public function handleDto(R11QueryDtoInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11QueryListInput
{
    /** @param list<R11Inner> $many */
    public function __construct(
        #[Query] public array $many = [],
    ) {
    }
}

final class R11QueryListHandler extends TypedHandler
{
    public function handleDto(R11QueryListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11DateDefaultInput
{
    public function __construct(
        #[Query] public \DateTimeImmutable $d = new \DateTimeImmutable('2020-01-01T00:00:00Z'),
    ) {
    }
}

final class R11DateDefaultHandler extends TypedHandler
{
    public function handleDto(R11DateDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11DtoDefaultInput
{
    public function __construct(
        #[Body] public R11Inner $i = new R11Inner(),
    ) {
    }
}

final class R11DtoDefaultHandler extends TypedHandler
{
    public function handleDto(R11DtoDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11ListObjectDefaultInput
{
    /** @param list<R11Inner> $items */
    public function __construct(
        #[Body] public array $items = [new R11Inner()],
    ) {
    }
}

final class R11ListObjectDefaultHandler extends TypedHandler
{
    public function handleDto(R11ListObjectDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11NullableItemsInput
{
    /** @param list<?int> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class R11NullableItemsHandler extends TypedHandler
{
    public function handleDto(R11NullableItemsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11UnionItemsInput
{
    /** @param list<int|string> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class R11UnionItemsHandler extends TypedHandler
{
    public function handleDto(R11UnionItemsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11NestedListsInput
{
    /** @param list<list<int>> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class R11NestedListsHandler extends TypedHandler
{
    public function handleDto(R11NestedListsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11AssocInput
{
    /** @param array<string, int> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class R11AssocHandler extends TypedHandler
{
    public function handleDto(R11AssocInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11OtherDocInput
{
    /** @param iterable<string> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class R11OtherDocHandler extends TypedHandler
{
    public function handleDto(R11OtherDocInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11PlainDateTimeInput
{
    public function __construct(
        #[Body] public \DateTime $d,
    ) {
    }
}

final class R11PlainDateTimeHandler extends TypedHandler
{
    public function handleDto(R11PlainDateTimeInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11DateInterfaceInput
{
    public function __construct(
        #[Body] public \DateTimeInterface $d,
    ) {
    }
}

final class R11DateInterfaceHandler extends TypedHandler
{
    public function handleDto(R11DateInterfaceInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11EmptySplitInput
{
    /** @param list<string> $a */
    public function __construct(
        #[Query] #[Cast(Split::class, '')] public array $a = [],
    ) {
    }
}

final class R11EmptySplitHandler extends TypedHandler
{
    public function handleDto(R11EmptySplitInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11SplitIntsInput
{
    /** @param list<int> $a */
    public function __construct(
        #[Query] #[Cast(Split::class, ',')] public array $a = [],
    ) {
    }
}

final class R11SplitIntsHandler extends TypedHandler
{
    public function handleDto(R11SplitIntsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11PathDashInput
{
    public function __construct(
        #[Path('a-b')] public int $x,
    ) {
    }
}

final class R11PathDashHandler extends TypedHandler
{
    public function handleDto(R11PathDashInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11HeaderUnderscoreInput
{
    public function __construct(
        #[Header('X_Foo')] public ?string $x = null,
    ) {
    }
}

final class R11HeaderUnderscoreHandler extends TypedHandler
{
    public function handleDto(R11HeaderUnderscoreInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11DocFormsInput
{
    /**
     * @param list<string>|null $a
     * @param ?list<string> $b
     * @param null|list<string> $c
     * @param string[] $d
     * @param non-empty-list<string> $e
     * @param list<string> $f
     * @phpstan-param list<int> $g
     * @psalm-param list<float> $h
     */
    public function __construct(
        #[Query] public ?array $a = null,
        #[Query] public ?array $b = null,
        #[Query] public ?array $c = null,
        #[Query] public ?array $d = null,
        #[Query] public ?array $e = null,
        #[Query] public ?array $f = null,
        #[Query] public array $g = [],
        #[Query] public array $h = [],
    ) {
    }
}

final class R11DocFormsHandler extends TypedHandler
{
    public function handleDto(R11DocFormsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11OptionalInput
{
    public function __construct(
        #[Body] public ?string $a = null,
        #[Body] public ?R11Color $c = null,
    ) {
    }
}

final class R11OptionalHandler extends TypedHandler
{
    public function handleDto(R11OptionalInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11FormInput
{
    public function __construct(
        #[Body] public ?string $a = null,
    ) {
    }
}

#[Consumes(MediaTypes::MULTIPART)]
final class R11MultipartHandler extends TypedHandler
{
    public function handleDto(R11FormInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class R11FormHandler extends TypedHandler
{
    public function handleDto(R11FormInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11TextInput
{
    public function __construct(
        #[Body] #[Length(max: 3)] public string $text,
    ) {
    }
}

final class R11TextHandler extends TypedHandler
{
    public function handleDto(R11TextInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11RangeInput
{
    public function __construct(
        #[Query] #[Range(max: 1.0E-12)] public float $x = 0.0,
    ) {
    }
}

final class R11RangeHandler extends TypedHandler
{
    public function handleDto(R11RangeInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11DateInput
{
    public function __construct(
        #[Body] public \DateTimeImmutable $at,
    ) {
    }
}

final class R11DateHandler extends TypedHandler
{
    public function handleDto(R11DateInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11HeaderInput
{
    public function __construct(
        #[Header('Authorization')] public ?string $auth = null,
    ) {
    }
}

final class R11HeaderHandler extends TypedHandler
{
    public function handleDto(R11HeaderInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

enum R11Color: string
{
    case Red = 'red';
}

final readonly class R11TrailingUnionInput
{
    /** @param list<int>|string $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class R11TrailingUnionHandler extends TypedHandler
{
    public function handleDto(R11TrailingUnionInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11ArrayOfArraysInput
{
    /** @param array<string[]> $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class R11ArrayOfArraysHandler extends TypedHandler
{
    public function handleDto(R11ArrayOfArraysInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11BracketUnionInput
{
    /** @param string[]|int $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class R11BracketUnionHandler extends TypedHandler
{
    public function handleDto(R11BracketUnionInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11ArrayOfListsInput
{
    /** @param array<string, list<int>> $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class R11ArrayOfListsHandler extends TypedHandler
{
    public function handleDto(R11ArrayOfListsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11UnionBracketsInput
{
    /** @param int|string[] $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class R11UnionBracketsHandler extends TypedHandler
{
    public function handleDto(R11UnionBracketsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11GenericOfArrayInput
{
    /** @param Collection<array> $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class R11GenericOfArrayHandler extends TypedHandler
{
    public function handleDto(R11GenericOfArrayInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11VarFallbackInput
{
    /** @param $a */
    public function __construct(
        /** @var list<int> */
        #[Body] public array $a = [],
    ) {
    }
}

final class R11VarFallbackHandler extends TypedHandler
{
    public function handleDto(R11VarFallbackInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11BadCaster implements CasterInterface
{
    public function __construct(string $format, $loose = null)
    {
        throw new \InvalidArgumentException('Bad format');
    }

    public function cast(mixed $value): mixed
    {
        return $value;
    }
}

final class R11NoCtorCaster implements CasterInterface
{
    public function cast(mixed $value): mixed
    {
        return $value;
    }
}

final readonly class R11BadCasterInput
{
    public function __construct(#[Query] #[Cast(R11BadCaster::class, 'x')] public ?string $a = null)
    {
    }
}

final class R11BadCasterHandler extends TypedHandler
{
    public function handleDto(R11BadCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11NoCtorCasterInput
{
    public function __construct(#[Query] #[Cast(R11NoCtorCaster::class, 'x')] public ?string $a = null)
    {
    }
}

final class R11NoCtorCasterHandler extends TypedHandler
{
    public function handleDto(R11NoCtorCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11BudgetInner
{
    public function __construct(#[Body] public string $a, #[Body] public string $b, #[Body] public string $c)
    {
    }
}

final readonly class R11BudgetObjectInput
{
    /** @param list<int> $ids */
    public function __construct(#[Body] public array $ids, #[Body] public R11BudgetInner $inner)
    {
    }
}

final class R11BudgetObjectHandler extends TypedHandler
{
    public function handleDto(R11BudgetObjectInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11NamedItem
{
    public function __construct(#[Body] #[Length(max: 3)] public string $name)
    {
    }
}

final readonly class R11ConstraintBudgetInput
{
    /** @param list<R11NamedItem> $items */
    public function __construct(#[Body] public array $items)
    {
    }
}

final class R11ConstraintBudgetHandler extends TypedHandler
{
    public function handleDto(R11ConstraintBudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R11BudgetTopInput
{
    /** @param list<int> $ids */
    public function __construct(#[Body] public array $ids, #[Body] public int $x, #[Body] public int $y)
    {
    }
}

final class R11BudgetTopHandler extends TypedHandler
{
    public function handleDto(R11BudgetTopInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
