<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class DtoPropertyTypeTest extends TestCase
{
    /**
     * @return \Iterator<string, array{class-string, string}>
     */
    public static function invalidHandlers(): \Iterator
    {
        yield 'nested dto in query' => [QueryDtoHandler::class, 'reads #[Query], which carries flat values'];
        yield 'list of dto in query' => [QueryListHandler::class, 'cannot be list<'];
        yield 'object default (date)' => [DateDefaultHandler::class, 'has a default of type DateTimeImmutable'];
        yield 'object default (dto)' => [DtoDefaultHandler::class, 'has a default of type Tomaj\\NetteApi\\Test\\Input\\ListedInner'];
        yield 'object default inside a list' => [ListObjectDefaultHandler::class, 'has a default of type'];
        yield 'list of nullable items' => [NullableItemsHandler::class, 'one non-nullable type'];
        yield 'list of unions' => [UnionItemsHandler::class, 'one non-nullable type'];
        yield 'list of lists' => [ListOfListsHandler::class, 'one non-nullable type'];
        yield 'associative array' => [AssocHandler::class, 'not a list'];
        yield 'unreadable doc type' => [OtherDocHandler::class, 'Found `iterable<string>`'];
        yield 'DateTime' => [PlainDateTimeHandler::class, 'Use DateTimeImmutable'];
        yield 'DateTimeInterface' => [DateInterfaceHandler::class, 'Use DateTimeImmutable'];
        yield 'empty split separator' => [EmptySplitHandler::class, 'Its constructor is __construct(string $separator)'];
        yield 'split into ints' => [SplitIntsHandler::class, 'Split gives list<string>'];
        yield 'list with a trailing union' => [TrailingUnionHandler::class, 'Found `list<int>|string`'];
        yield 'array of arrays' => [ArrayOfArraysHandler::class, 'not a list'];
        yield 'brackets with a trailing union' => [BracketUnionHandler::class, 'Found `string[]|int`'];
        yield 'array of lists' => [ArrayOfListsHandler::class, 'not a list'];
        yield 'union before brackets' => [UnionBracketsHandler::class, 'Found `int|string[]`'];
        yield 'generic of array' => [GenericOfArrayHandler::class, 'Found `Collection<array>`'];
        yield 'caster constructor with typed and untyped parameters' => [BadCasterHandler::class, 'Its constructor is __construct(string $format, $loose)'];
        yield 'caster without a constructor' => [NoCtorCasterHandler::class, 'Its constructor is __construct()'];
        yield 'dash in a path name' => [PathDashHandler::class, 'can only have letters, digits and underscores'];
        yield 'underscore in a header name' => [HeaderUnderscoreHandler::class, "Use #[Header('X-Foo')]"];
        yield 'authorization header' => [AuthorizationHeaderHandler::class, 'AuthorizationHeaderInput::$token reads the Authorization header, which OpenAPI does not describe as a parameter.'];
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

    public function testPathNameMessageSuggestsTheCorrectedName(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Use for example #[Path('a_b')].");
        DtoCompiler::compile(PathDashHandler::class);
    }

    public function testSplitSeparatorMessageHasNoDoublePeriod(): void
    {
        try {
            DtoCompiler::compile(EmptySplitHandler::class);
            $this->fail('compiled');
        } catch (\LogicException $exception) {
            $this->assertStringNotContainsString('..', $exception->getMessage());
            $this->assertStringContainsString("#[Cast(Split::class, ',')]. Its constructor", $exception->getMessage());
            $this->assertStringNotContainsString('d.m.Y', $exception->getMessage());
        }
    }

    /**
     * @return \Iterator<string, array{class-string<TypedHandler>, string}>
     */
    public static function invalid(): \Iterator
    {
        yield 'associative var with a description' => [VarAssocHandler::class, 'documents `array<string, list<int>>`, an array that is not a list'];
        yield 'array shape item' => [ShapeItemHandler::class, 'declare an item DTO'];
        yield 'positive-int' => [PositiveIntHandler::class, 'PHPStan refinements such as positive-int'];
        yield 'non-empty-string' => [NonEmptyStringHandler::class, 'documents list<non-empty-string>. PHPStan refinements'];
        yield 'integer range' => [IntRangeHandler::class, 'documents list<int<0, 10>>. PHPStan refinements'];
        yield 'generic item' => [GenericItemHandler::class, 'documents list<Collection<Item>>. PHPStan refinements'];
        yield 'generic item with a list inside' => [ListInsideGenericHandler::class, 'documents list<Collection<list>>. PHPStan refinements'];
        yield 'dashed name that is no refinement' => [DashedItemHandler::class, 'documents list<foo-bar\Baz>, and that type was not found'];
        yield 'DateTime of Nette' => [NetteDateHandler::class, 'has type Nette\Utils\DateTime. Use DateTimeImmutable itself'];
        yield 'DateTimeImmutable subclass' => [MyDateHandler::class, 'has type Tomaj\NetteApi\Test\Input\CustomDate. Use DateTimeImmutable itself'];
        yield 'empty path name' => [EmptyPathHandler::class, "for example #[Path('my_name')]"];
        yield 'empty query name' => [EmptyQueryHandler::class, "for example #[Query('my_name')]"];
        yield 'empty body name' => [EmptyBodyHandler::class, "for example #[Body('my_name')]"];
        yield 'empty header name' => [EmptyHeaderHandler::class, "for example #[Header('X-Request-Id')]"];
        yield 'name that starts with a digit' => [DigitNameHandler::class, 'must start with a letter or an underscore'];
        yield 'field named body' => [BodyNamedHandler::class, 'Tomaj\\NetteApi\\Test\\Input\\BodyNamedInput::$text is read as \'body\', the key under which errors of the request as a whole are reported. Give it another input name, for example #[Body(\'payload\')].'];
        yield 'query named body' => [QueryNamedBodyHandler::class, "for example #[Query('payload')]"];
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

    public function testListOfPureEnumsIsNotABackedEnum(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('PureEnumListInput::$suits uses %s, which is not a backed enum.', Suit::class));
        DtoCompiler::compile(PureEnumListHandler::class);
    }
}

final readonly class ListedInner
{
    public function __construct(#[Body] public string $q = 'x')
    {
    }
}

final readonly class QueryDtoInput
{
    public function __construct(
        #[Query] public ListedInner $x,
    ) {
    }
}

final class QueryDtoHandler extends TypedHandler
{
    public function handleDto(QueryDtoInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class QueryListInput
{
    /** @param list<ListedInner> $many */
    public function __construct(
        #[Query] public array $many = [],
    ) {
    }
}

final class QueryListHandler extends TypedHandler
{
    public function handleDto(QueryListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class DateDefaultInput
{
    public function __construct(
        #[Query] public \DateTimeImmutable $d = new \DateTimeImmutable('2020-01-01T00:00:00Z'),
    ) {
    }
}

final class DateDefaultHandler extends TypedHandler
{
    public function handleDto(DateDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class DtoDefaultInput
{
    public function __construct(
        #[Body] public ListedInner $i = new ListedInner(),
    ) {
    }
}

final class DtoDefaultHandler extends TypedHandler
{
    public function handleDto(DtoDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ListObjectDefaultInput
{
    /** @param list<ListedInner> $items */
    public function __construct(
        #[Body] public array $items = [new ListedInner()],
    ) {
    }
}

final class ListObjectDefaultHandler extends TypedHandler
{
    public function handleDto(ListObjectDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NullableItemsInput
{
    /** @param list<?int> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class NullableItemsHandler extends TypedHandler
{
    public function handleDto(NullableItemsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class UnionItemsInput
{
    /** @param list<int|string> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class UnionItemsHandler extends TypedHandler
{
    public function handleDto(UnionItemsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ListOfListsInput
{
    /** @param list<list<int>> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class ListOfListsHandler extends TypedHandler
{
    public function handleDto(ListOfListsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class AssocInput
{
    /** @param array<string, int> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class AssocHandler extends TypedHandler
{
    public function handleDto(AssocInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class OtherDocInput
{
    /** @param iterable<string> $a */
    public function __construct(
        #[Body] public array $a = [],
    ) {
    }
}

final class OtherDocHandler extends TypedHandler
{
    public function handleDto(OtherDocInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class PlainDateTimeInput
{
    public function __construct(
        #[Body] public \DateTime $d,
    ) {
    }
}

final class PlainDateTimeHandler extends TypedHandler
{
    public function handleDto(PlainDateTimeInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class DateInterfaceInput
{
    public function __construct(
        #[Body] public \DateTimeInterface $d,
    ) {
    }
}

final class DateInterfaceHandler extends TypedHandler
{
    public function handleDto(DateInterfaceInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class EmptySplitInput
{
    /** @param list<string> $a */
    public function __construct(
        #[Query] #[Cast(Split::class, '')] public array $a = [],
    ) {
    }
}

final class EmptySplitHandler extends TypedHandler
{
    public function handleDto(EmptySplitInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class SplitIntsInput
{
    /** @param list<int> $a */
    public function __construct(
        #[Query] #[Cast(Split::class, ',')] public array $a = [],
    ) {
    }
}

final class SplitIntsHandler extends TypedHandler
{
    public function handleDto(SplitIntsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class PathDashInput
{
    public function __construct(
        #[Path('a-b')] public int $x,
    ) {
    }
}

final class PathDashHandler extends TypedHandler
{
    public function handleDto(PathDashInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class HeaderUnderscoreInput
{
    public function __construct(
        #[Header('X_Foo')] public ?string $x = null,
    ) {
    }
}

final class HeaderUnderscoreHandler extends TypedHandler
{
    public function handleDto(HeaderUnderscoreInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class AuthorizationHeaderInput
{
    public function __construct(
        #[Header('Authorization')] public ?string $token = null,
    ) {
    }
}

final class AuthorizationHeaderHandler extends TypedHandler
{
    public function handleDto(AuthorizationHeaderInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class TrailingUnionInput
{
    /** @param list<int>|string $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class TrailingUnionHandler extends TypedHandler
{
    public function handleDto(TrailingUnionInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ArrayOfArraysInput
{
    /** @param array<string[]> $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class ArrayOfArraysHandler extends TypedHandler
{
    public function handleDto(ArrayOfArraysInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BracketUnionInput
{
    /** @param string[]|int $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class BracketUnionHandler extends TypedHandler
{
    public function handleDto(BracketUnionInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ArrayOfListsInput
{
    /** @param array<string, list<int>> $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class ArrayOfListsHandler extends TypedHandler
{
    public function handleDto(ArrayOfListsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class UnionBracketsInput
{
    /** @param int|string[] $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class UnionBracketsHandler extends TypedHandler
{
    public function handleDto(UnionBracketsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class GenericOfArrayInput
{
    /** @param Collection<array> $a */
    public function __construct(#[Body] public array $a = [])
    {
    }
}

final class GenericOfArrayHandler extends TypedHandler
{
    public function handleDto(GenericOfArrayInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadCaster implements CasterInterface
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

final class NoCtorCaster implements CasterInterface
{
    public function cast(mixed $value): mixed
    {
        return $value;
    }
}

final readonly class BadCasterInput
{
    public function __construct(#[Query] #[Cast(BadCaster::class, 'x')] public ?string $a = null)
    {
    }
}

final class BadCasterHandler extends TypedHandler
{
    public function handleDto(BadCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NoCtorCasterInput
{
    public function __construct(#[Query] #[Cast(NoCtorCaster::class, 'x')] public ?string $a = null)
    {
    }
}

final class NoCtorCasterHandler extends TypedHandler
{
    public function handleDto(NoCtorCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class VarAssocInput
{
    public function __construct(
        /** @var array<string, list<int>> Lists by name */
        #[Body] public array $lists = [],
    ) {
    }
}

final class VarAssocHandler extends TypedHandler
{
    public function handleDto(VarAssocInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ShapeItemInput
{
    /** @param list<array{a: int}> $shapes */
    public function __construct(#[Body] public array $shapes = [])
    {
    }
}

final class ShapeItemHandler extends TypedHandler
{
    public function handleDto(ShapeItemInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class PositiveIntInput
{
    /** @param list<positive-int> $ids */
    public function __construct(#[Body] public array $ids = [])
    {
    }
}

final class PositiveIntHandler extends TypedHandler
{
    public function handleDto(PositiveIntInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NonEmptyStringInput
{
    /** @param list<non-empty-string> $names */
    public function __construct(#[Body] public array $names = [])
    {
    }
}

final class NonEmptyStringHandler extends TypedHandler
{
    public function handleDto(NonEmptyStringInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class IntRangeInput
{
    /** @param list<int<0, 10>> $ids */
    public function __construct(#[Body] public array $ids = [])
    {
    }
}

final class IntRangeHandler extends TypedHandler
{
    public function handleDto(IntRangeInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class GenericItemInput
{
    /** @param list<Collection<Item>> $items */
    public function __construct(#[Body] public array $items = [])
    {
    }
}

final class GenericItemHandler extends TypedHandler
{
    public function handleDto(GenericItemInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NetteDateInput
{
    public function __construct(#[Body] public ?DateTime $at = null)
    {
    }
}

final class NetteDateHandler extends TypedHandler
{
    public function handleDto(NetteDateInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

class CustomDate extends \DateTimeImmutable
{
}

final readonly class MyDateInput
{
    public function __construct(#[Body] public ?CustomDate $at = null)
    {
    }
}

final class MyDateHandler extends TypedHandler
{
    public function handleDto(MyDateInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class EmptyPathInput
{
    public function __construct(#[Path('')] public int $id)
    {
    }
}

final class EmptyPathHandler extends TypedHandler
{
    public function handleDto(EmptyPathInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class EmptyQueryInput
{
    public function __construct(#[Query('')] public ?int $page = null)
    {
    }
}

final class EmptyQueryHandler extends TypedHandler
{
    public function handleDto(EmptyQueryInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class EmptyBodyInput
{
    public function __construct(#[Body('')] public ?int $page = null)
    {
    }
}

final class EmptyBodyHandler extends TypedHandler
{
    public function handleDto(EmptyBodyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class EmptyHeaderInput
{
    public function __construct(#[Header('')] public ?string $id = null)
    {
    }
}

final class EmptyHeaderHandler extends TypedHandler
{
    public function handleDto(EmptyHeaderInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class DigitNameInput
{
    public function __construct(#[Query('0a')] public ?int $page = null)
    {
    }
}

final class DigitNameHandler extends TypedHandler
{
    public function handleDto(DigitNameInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ListInsideGenericInput
{
    /** @param list<Collection<list>> $items */
    public function __construct(#[Body] public array $items = [])
    {
    }
}

final class ListInsideGenericHandler extends TypedHandler
{
    public function handleDto(ListInsideGenericInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class DashedItemInput
{
    /** @param list<foo-bar\Baz> $items */
    public function __construct(#[Body] public array $items = [])
    {
    }
}

final class DashedItemHandler extends TypedHandler
{
    public function handleDto(DashedItemInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

enum Suit
{
    case Hearts;
    case Spades;
}

final readonly class PureEnumListInput
{
    /** @param list<Suit> $suits */
    public function __construct(#[Query] public array $suits = [])
    {
    }
}

final class PureEnumListHandler extends TypedHandler
{
    public function handleDto(PureEnumListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BodyNamedInput
{
    public function __construct(#[Body('body')] public string $text)
    {
    }
}

final class BodyNamedHandler extends TypedHandler
{
    public function handleDto(BodyNamedInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class QueryNamedBodyInput
{
    public function __construct(#[Query] public ?string $page = null, #[Query] public ?string $body = null)
    {
    }
}

final class QueryNamedBodyHandler extends TypedHandler
{
    public function handleDto(QueryNamedBodyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
