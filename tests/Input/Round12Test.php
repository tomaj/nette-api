<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Consumes;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\DtoMapper;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

final class Round12Test extends TestCase
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

    public function testAPostFormWithMoreFieldsThanMaxInputVarsIsRejected(): void
    {
        $limit = (int) ini_get('max_input_vars');
        // PHP keeps the first `limit` variables and drops the rest without a word
        $_POST = array_fill_keys(array_map(static fn (int $i): string => 'f' . $i, range(1, $limit)), '1');

        $errors = $this->post('application/x-www-form-urlencoded', self::pairs('f', $limit + 1), new R12FormHandler());

        $this->assertSame(['body' => ['Too many form fields']], $errors);
    }

    public function testAPostFormWithExactlyTheLimitIsAccepted(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_POST = array_fill_keys(array_map(static fn (int $i): string => 'f' . $i, range(1, $limit)), '1');

        $this->assertNull($this->post('application/x-www-form-urlencoded', self::pairs('f', $limit), new R12FormHandler()));
    }

    public function testAPostFormIsCountedWithItsNestedValues(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_POST = ['a' => array_fill(0, $limit, '1')];
        $raw = implode('&', array_map(static fn (int $i): string => 'a[]=' . $i, range(1, $limit + 1)));

        $this->assertSame(['body' => ['Too many form fields']], $this->post('application/x-www-form-urlencoded', $raw, new R12FormHandler()));
    }

    public function testASmallPostFormIsNotParsedAgain(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_POST = ['a' => 'x'];

        // the body is not even looked at: PHP cannot have cut a form that fits
        $this->assertNull($this->post('application/x-www-form-urlencoded', self::pairs('f', $limit + 1), new R12FormHandler()));
    }

    public function testAMultipartPostIsLeftToPhp(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $_POST = array_fill_keys(array_map(static fn (int $i): string => 'f' . $i, range(1, $limit)), '1');

        // PHP does not give a multipart body back, so a form that was cut cannot be told from a complete one
        $this->assertNull($this->post('multipart/form-data; boundary=x', self::pairs('f', $limit + 1), new R12MultipartHandler()));
    }

    #[DataProvider('charsets')]
    public function testJsonCharsets(string $contentType, ?string $rejected): void
    {
        $errors = $this->post($contentType, '{"a":"x"}', new R12FormHandler());

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

    /**
     * @return \Iterator<string, array{class-string<TypedHandler>, string}>
     */
    public static function descriptions(): \Iterator
    {
        yield 'var with a description' => [R12VarDescriptionHandler::class, 'string'];
        yield 'var without a space before the end of the comment' => [R12VarTightHandler::class, 'int'];
        yield 'non-empty-list with a description' => [R12VarNonEmptyHandler::class, 'string'];
        yield 'phpstan var with a nested generic and a description' => [R12VarGenericHandler::class, 'int'];
    }

    /**
     * @param class-string<TypedHandler> $handler
     */
    #[DataProvider('descriptions')]
    public function testAVarTypeMayBeFollowedByADescription(string $handler, string $item): void
    {
        $this->assertSame($item, DtoCompiler::compile($handler)->fields[0]->listItem);
    }

    /**
     * @return \Iterator<string, array{class-string<TypedHandler>, string}>
     */
    public static function invalid(): \Iterator
    {
        yield 'associative var with a description' => [R12VarAssocHandler::class, 'documents `array<string, list<int>>`, an array that is not a list'];
        yield 'array shape item' => [R12ShapeItemHandler::class, 'declare an item DTO'];
        yield 'positive-int' => [R12PositiveIntHandler::class, 'PHPStan refinements such as positive-int'];
        yield 'non-empty-string' => [R12NonEmptyStringHandler::class, 'documents list<non-empty-string>. PHPStan refinements'];
        yield 'integer range' => [R12IntRangeHandler::class, 'documents list<int<0, 10>>. PHPStan refinements'];
        yield 'generic item' => [R12GenericItemHandler::class, 'documents list<Collection<Item>>. PHPStan refinements'];
        yield 'generic item with a list inside' => [R12ListInsideGenericHandler::class, 'documents list<Collection<list>>. PHPStan refinements'];
        yield 'dashed name that is no refinement' => [R12DashedItemHandler::class, 'documents list<foo-bar\Baz>, and that type was not found'];
        yield 'DateTime of Nette' => [R12NetteDateHandler::class, 'has type Nette\Utils\DateTime. Use DateTimeImmutable itself'];
        yield 'DateTimeImmutable subclass' => [R12MyDateHandler::class, 'has type Tomaj\NetteApi\Test\Input\R12MyDate. Use DateTimeImmutable itself'];
        yield 'empty path name' => [R12EmptyPathHandler::class, "for example #[Path('my_name')]"];
        yield 'empty query name' => [R12EmptyQueryHandler::class, "for example #[Query('my_name')]"];
        yield 'empty body name' => [R12EmptyBodyHandler::class, "for example #[Body('my_name')]"];
        yield 'empty header name' => [R12EmptyHeaderHandler::class, "for example #[Header('X-Request-Id')]"];
        yield 'name that starts with a digit' => [R12DigitNameHandler::class, 'must start with a letter or an underscore'];
    }

    /**
     * @param class-string<TypedHandler> $handler
     */
    #[DataProvider('invalid')]
    public function testCompileMessages(string $handler, string $needle): void
    {
        try {
            DtoCompiler::compile($handler);
            $this->fail('compiled');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString($needle, $exception->getMessage());
        }
    }

    /**
     * @return \Iterator<string, array{string, string|null}>
     */
    public static function patterns(): \Iterator
    {
        yield 'unicode property with a quantifier' => ['/^\p{L}+$/u', '^\p{L}+$'];
        yield 'braces after an escaped brace' => ['/\{3}+/', '\{3}+'];
        yield 'escaped backslash and A' => ['/\\\\A/', '\\\\A'];
        yield 'escaped backslash and a letter that is only PCRE after one more backslash' => ['/a\\\\\\\\A/', 'a\\\\\\\\A'];
        yield 'escaped plus then a quantifier' => ['/a\++/', 'a\++'];
        yield 'lazy quantifier' => ['/[0-9]+?/', '[0-9]+?'];
        yield 'lookbehind' => ['/(?<=a)b/', '(?<=a)b'];
        yield 'named group' => ['/(?<name>a)/', '(?<name>a)'];
        yield 'hex code point' => ['/^\x{41}+$/u', null];
        yield 'hex byte' => ['/^\x41+$/', '^\x41+$'];
        yield 'posix class' => ['/[[:alpha:]]/', null];
        yield 'comment' => ['/a(?#note)b/', null];
        yield 'branch reset' => ['/(?|a|b)/', null];
        yield 'escape character' => ['/a\e/', null];
        yield 'back reference g' => ['/(a)\g{1}/', null];
        yield 'escaped backslash then possessive' => ['/\\\\++/', null];
        yield 'three backslashes then A' => ['/\\\\\A/', null];
        yield 'possessive after a number of repetitions' => ['/a{2}+/', null];
        yield 'possessive after a range' => ['/[a-z]{2,}+/', null];
        yield 'relative recursion' => ['/(a)(?-1)/', null];
        yield 'numbered recursion' => ['/(a)(?1)/', null];
        yield 'named recursion' => ['/(?<n>a)(?&n)/', null];
        yield 'single quoted group name' => ["/(?'n'a)/", null];
        yield 'non newline escape' => ['/a\N/', null];
        yield 'possessive question' => ['/a?+/', null];
        yield 'possessive star' => ['/a*+/', null];
    }

    #[DataProvider('patterns')]
    public function testPatternOmissionFromOpenApi(string $pattern, ?string $expression): void
    {
        $this->assertSame($expression, new Pattern($pattern)->expression());
    }

    public function testTheNoteAboutAnExhaustedBudgetBelongsToTheFirstFieldWhenNothingReadsABody(): void
    {
        $_GET = ['ids' => array_fill(0, 150, 'x')];
        $metadata = DtoCompiler::compile(R12QueryBudgetHandler::class);
        $mapper = new DtoMapper($metadata);

        $first = $mapper->errors($metadata->fields[0]);
        $this->assertCount(101, $first);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $first['body']);
        $this->assertSame([], $mapper->errors($metadata->fields[1]));
    }

    public function testTheNoteBelongsToTheFirstBodyFieldEvenWhenAQueryFieldComesFirst(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('{"ids":' . json_encode(array_fill(0, 150, 'x')) . '}');
        $metadata = DtoCompiler::compile(R12MixedBudgetHandler::class);
        $mapper = new DtoMapper($metadata);

        $this->assertSame([], $mapper->errors($metadata->fields[0]));
        $this->assertArrayHasKey('body', $mapper->errors($metadata->fields[1]));
    }

    public function testAnErrorOfTheBodyAndTheNoteShareTheKey(): void
    {
        $this->assertNull($this->post('application/json', '{"a":"x"}', new R12FormHandler()));
        RequestInput::reset();
        $errors = $this->post('application/json', '{"ids":' . json_encode(array_fill(0, 101, 'x')) . '}', new R12BodyBudgetHandler());

        $this->assertCount(101, $errors ?? []);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body'] ?? []);
    }

    public function testRangePrintsMinusZeroAsZero(): void
    {
        $context = new ConstraintContext('x', self::class, [], []);

        $this->assertSame('Value must be at most 0', new Range(max: -0.0)->validate(1, $context));
    }
}

final readonly class R12FormInput
{
    public function __construct(#[Body] public ?string $a = null)
    {
    }
}

final class R12FormHandler extends TypedHandler
{
    public function handleDto(R12FormInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[Consumes(MediaTypes::MULTIPART)]
final class R12MultipartHandler extends TypedHandler
{
    public function handleDto(R12FormInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12VarDescriptionInput
{
    public function __construct(
        /** @var list<string> Names of the things */
        #[Body] public array $names = [],
    ) {
    }
}

final class R12VarDescriptionHandler extends TypedHandler
{
    public function handleDto(R12VarDescriptionInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12VarTightInput
{
    public function __construct(
        /** @var list<int>*/
        #[Body] public array $ids = [],
    ) {
    }
}

final class R12VarTightHandler extends TypedHandler
{
    public function handleDto(R12VarTightInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12VarNonEmptyInput
{
    public function __construct(
        /** @var non-empty-list<string> Names of the things */
        #[Body] public array $names = ['a'],
    ) {
    }
}

final class R12VarNonEmptyHandler extends TypedHandler
{
    public function handleDto(R12VarNonEmptyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12VarGenericInput
{
    public function __construct(
        /** @phpstan-var list<int> Numbers: any of them */
        #[Body] public array $numbers = [],
    ) {
    }
}

final class R12VarGenericHandler extends TypedHandler
{
    public function handleDto(R12VarGenericInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12VarAssocInput
{
    public function __construct(
        /** @var array<string, list<int>> Lists by name */
        #[Body] public array $lists = [],
    ) {
    }
}

final class R12VarAssocHandler extends TypedHandler
{
    public function handleDto(R12VarAssocInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12ShapeItemInput
{
    /** @param list<array{a: int}> $shapes */
    public function __construct(#[Body] public array $shapes = [])
    {
    }
}

final class R12ShapeItemHandler extends TypedHandler
{
    public function handleDto(R12ShapeItemInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12PositiveIntInput
{
    /** @param list<positive-int> $ids */
    public function __construct(#[Body] public array $ids = [])
    {
    }
}

final class R12PositiveIntHandler extends TypedHandler
{
    public function handleDto(R12PositiveIntInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12NonEmptyStringInput
{
    /** @param list<non-empty-string> $names */
    public function __construct(#[Body] public array $names = [])
    {
    }
}

final class R12NonEmptyStringHandler extends TypedHandler
{
    public function handleDto(R12NonEmptyStringInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12IntRangeInput
{
    /** @param list<int<0, 10>> $ids */
    public function __construct(#[Body] public array $ids = [])
    {
    }
}

final class R12IntRangeHandler extends TypedHandler
{
    public function handleDto(R12IntRangeInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12GenericItemInput
{
    /** @param list<Collection<Item>> $items */
    public function __construct(#[Body] public array $items = [])
    {
    }
}

final class R12GenericItemHandler extends TypedHandler
{
    public function handleDto(R12GenericItemInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12NetteDateInput
{
    public function __construct(#[Body] public ?DateTime $at = null)
    {
    }
}

final class R12NetteDateHandler extends TypedHandler
{
    public function handleDto(R12NetteDateInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

class R12MyDate extends \DateTimeImmutable
{
}

final readonly class R12MyDateInput
{
    public function __construct(#[Body] public ?R12MyDate $at = null)
    {
    }
}

final class R12MyDateHandler extends TypedHandler
{
    public function handleDto(R12MyDateInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12EmptyPathInput
{
    public function __construct(#[Path('')] public int $id)
    {
    }
}

final class R12EmptyPathHandler extends TypedHandler
{
    public function handleDto(R12EmptyPathInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12EmptyQueryInput
{
    public function __construct(#[Query('')] public ?int $page = null)
    {
    }
}

final class R12EmptyQueryHandler extends TypedHandler
{
    public function handleDto(R12EmptyQueryInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12EmptyBodyInput
{
    public function __construct(#[Body('')] public ?int $page = null)
    {
    }
}

final class R12EmptyBodyHandler extends TypedHandler
{
    public function handleDto(R12EmptyBodyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12EmptyHeaderInput
{
    public function __construct(#[Header('')] public ?string $id = null)
    {
    }
}

final class R12EmptyHeaderHandler extends TypedHandler
{
    public function handleDto(R12EmptyHeaderInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12DigitNameInput
{
    public function __construct(#[Query('0a')] public ?int $page = null)
    {
    }
}

final class R12DigitNameHandler extends TypedHandler
{
    public function handleDto(R12DigitNameInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12QueryBudgetInput
{
    /** @param list<int> $ids */
    public function __construct(#[Query] public array $ids = [], #[Query] public int $page = 1)
    {
    }
}

final class R12QueryBudgetHandler extends TypedHandler
{
    public function handleDto(R12QueryBudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12BodyBudgetInput
{
    /** @param list<int> $ids */
    public function __construct(#[Body] public array $ids = [])
    {
    }
}

final class R12BodyBudgetHandler extends TypedHandler
{
    public function handleDto(R12BodyBudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12ListInsideGenericInput
{
    /** @param list<Collection<list>> $items */
    public function __construct(#[Body] public array $items = [])
    {
    }
}

final class R12ListInsideGenericHandler extends TypedHandler
{
    public function handleDto(R12ListInsideGenericInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12DashedItemInput
{
    /** @param list<foo-bar\Baz> $items */
    public function __construct(#[Body] public array $items = [])
    {
    }
}

final class R12DashedItemHandler extends TypedHandler
{
    public function handleDto(R12DashedItemInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class R12MixedBudgetInput
{
    /** @param list<int> $ids */
    public function __construct(#[Query] public int $page = 1, #[Body] public array $ids = [])
    {
    }
}

final class R12MixedBudgetHandler extends TypedHandler
{
    public function handleDto(R12MixedBudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
