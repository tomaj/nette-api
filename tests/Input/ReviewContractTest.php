<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Tomaj\NetteApi\Input\DtoMetadata;
use Tomaj\NetteApi\Input\Field;
use Tomaj\NetteApi\Test\Input\Fixtures\UserStatus;
use Tomaj\NetteApi\Test\Input\Fixtures\Dependency\DependencyItemEnum;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;
use Tomaj\NetteApi\Input\OpenApiCasterInterface;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\CastException;
use Tomaj\NetteApi\Input\ConversionFailed;
use Tomaj\NetteApi\Input\DateFormat;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\ScalarCoercer;
use Tomaj\NetteApi\Input\SchemaBuilder;
use Tomaj\NetteApi\Input\UnixTimestamp;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\Dependency\DependencyHandler;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\ConstraintInterface;
use Tomaj\NetteApi\Validation\Pattern;

final class ReviewContractTest extends TestCase
{
    protected function setUp(): void
    {
        RequestInput::reset();
        DtoMetadataRegistry::reset();
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        RequestInput::reset();
        DtoMetadataRegistry::reset();
        $_GET = [];
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['REQUEST_METHOD']);
    }

    public function testValueObjectCasterIsNotCompiledAsADto(): void
    {
        $this->json(['price' => '12.50', 'prices' => ['1.00', '2.25']]);
        $handler = new MoneyHandler();
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError(), (string) json_encode($processor->getErrors()));
        $this->assertSame(['price' => 1250, 'prices' => [100, 225]], $handler->handle($processor->getValues())->getPayload());

        $fields = DtoCompiler::compile(MoneyHandler::class)->fields;
        $this->assertSame([], $fields[0]->children);
        $this->assertSame(['type' => 'string', 'example' => '12.50'], SchemaBuilder::build($fields[0], true));
        $this->assertNull($fields[1]->item);

        $this->json(['price' => 'abc', 'prices' => []]);
        $this->assertSame(['price' => ['Use a decimal amount, for example 12.50']], $this->errors(new MoneyHandler()));
    }

    public function testNamedCasterArgumentsSurviveTheCompiledContainer(): void
    {
        $_GET = ['value' => 'X', 'other' => 'Y'];
        $this->assertSame(['value' => 'STARTXEND', 'other' => 'AYB'], $this->payload(new NamedCasterHandler()));
        $compiled = DtoCompiler::compile(WireSchemaHandler::class);
        $imported = DtoMetadata::import($compiled->export());
        $this->assertSame($compiled->fields[0]->casterSchema, $imported->fields[0]->casterSchema);
        $this->assertSame('integer', $imported->fields[0]->casterSchema['type'] ?? null);

        $export = $compiled->fields[0]->export();
        $export['casterSchema'] = [0 => 'stray', 'type' => 'integer'];
        $this->assertSame(['type' => 'integer'], Field::import($export)->casterSchema);

        DtoMetadataRegistry::install([NamedCasterHandler::class => DtoCompiler::compile(NamedCasterHandler::class)->export()]);
        $this->assertTrue(DtoMetadataRegistry::installs(NamedCasterHandler::class));
        $this->assertSame(['value' => 'STARTXEND', 'other' => 'AYB'], $this->payload(new NamedCasterHandler()));
    }

    public function testNulByteIsAFieldError(): void
    {
        try {
            new DateFormat('d.m.Y')->cast("24.12.2026\0");
            $this->fail('NUL byte was accepted');
        } catch (CastException $exception) {
            $this->assertSame('Date must match d.m.Y, for example 24.12.2026', $exception->getMessage());
        }

        $this->json(['day' => "24.12.2026\u{0}"]);
        $this->assertSame(['day' => ['Date must match d.m.Y, for example 24.12.2026']], $this->errors(new NulDateHandler()));
    }

    public function testCompiledCasterSchemaUsesTheWireType(): void
    {
        $fields = DtoCompiler::compile(WireSchemaHandler::class)->fields;
        $this->assertSame('integer', SchemaBuilder::build($fields[0], true)['type']);
        $this->assertSame(new UnixTimestamp()->openApiSchema()['example'], SchemaBuilder::build($fields[0], true)['example']);
        $this->assertSame(['type' => 'string'], SchemaBuilder::build($fields[1], true));
    }

    public function testSiblingDefaultsAreVisibleToConstraints(): void
    {
        $this->assertCount(2, DtoCompiler::compile(SiblingDefaultsHandler::class)->fields);
        $this->assertCompileFails(BadSiblingDefaultsHandler::class, 'BadSiblingDefaultsInput::$right default does not satisfy #[SameAs]: Must equal left');
    }

    public function testDefaultsAfterARequiredFieldAreChecked(): void
    {
        $this->assertCompileFails(RequiredThenBadDefaultHandler::class, 'RequiredThenBadDefaultInput::$right default does not satisfy #[SameAs]');
    }

    public function testValidListDefaultsOfEveryItemType(): void
    {
        $fields = DtoCompiler::compile(ListDefaultsHandler::class)->fields;
        $this->assertSame([[1, 2.5], [true], ['a'], [UserStatus::Active], ['x']], array_map(static fn (Field $field): mixed => $field->default, $fields));
        $this->assertCompileFails(BadFloatListDefaultHandler::class, 'default item 1 is string, but the property is list<float>');
        $this->assertCompileFails(BadBoolListDefaultHandler::class, 'default item 0 is int, but the property is list<bool>');
        $this->assertCompileFails(BadStringListDefaultHandler::class, 'default item 0 is int, but the property is list<string>');
        $this->assertCompileFails(BadEnumListDefaultHandler::class, 'default item 0 is string, but the property is list<' . UserStatus::class . '>');
        $this->assertCompileFails(BadCastListDefaultHandler::class, 'default item 0 is int, but the property is list<string>');
    }

    public function testLengthOnAListIsCheckedBeforeItsItems(): void
    {
        $_GET = ['ids' => ['x', 'y', 'z']];
        $this->assertSame(['ids' => ['Value must have between 1 and 2 items']], $this->errors(new LengthListHandler()));
        RequestInput::reset();
        $_GET = ['ids' => []];
        $this->assertSame(['ids' => ['Value must have between 1 and 2 items']], $this->errors(new LengthListHandler()));
        RequestInput::reset();
        $_GET = ['ids' => ['1', '2'], 'min' => ['x']];
        $this->assertSame(['min[0]' => ['Value must be an integer']], $this->errors(new LengthListHandler()));
        RequestInput::reset();
        $_GET = ['ids' => ['1'], 'min' => ['4', '5', '6']];
        $this->assertSame(['ids' => [1], 'min' => [4, 5, 6]], $this->payload(new LengthListHandler()));
    }

    public function testDefaultsInTheSchema(): void
    {
        $fields = DtoCompiler::compile(SchemaDefaultsHandler::class)->fields;
        $this->assertSame('active', SchemaBuilder::build($fields[0], true)['default']);
        $this->assertSame('a,b', SchemaBuilder::build($fields[1], true)['default']);
        $this->assertArrayNotHasKey('default', SchemaBuilder::build($fields[2], true));
        $this->assertSame(2, SchemaBuilder::build($fields[3], true)['default']);
    }

    public function testListDefaultItemsAreChecked(): void
    {
        $this->assertCompileFails(BadListDefaultHandler::class, "BadListDefaultInput::\$items default item 1 is string, but the property is list<int>");
        $this->assertCompileFails(MapDefaultHandler::class, 'MapDefaultInput::$items default must be a list');
    }

    public function testConstructorParametersThatTheMapperCannotPass(): void
    {
        $this->assertCompileFails(SecretArgumentHandler::class, 'SecretArgumentInput::__construct() has the required parameter $secret that is not a promoted property');
        $this->assertCompileFails(VariadicArgumentHandler::class, 'VariadicArgumentInput::__construct() parameter $rest is variadic');
        $this->assertCompileFails(ReferenceArgumentHandler::class, 'ReferenceArgumentInput::__construct() parameter $page is passed by reference');
        $this->assertCount(1, DtoCompiler::compile(OptionalArgumentHandler::class)->fields);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedIntegers(): iterable
    {
        foreach (['++0', '--0', '+-0', '-+000', "1\n", ' 1', '1 ', '+', '-', ''] as $value) {
            yield json_encode($value, JSON_THROW_ON_ERROR) => [$value];
        }
    }

    #[DataProvider('malformedIntegers')]
    public function testMalformedIntegersAreRejected(string $value): void
    {
        $this->expectException(ConversionFailed::class);
        ScalarCoercer::toInt($value);
    }

    public function testSingleSignedIntegersAndAnchoredPatterns(): void
    {
        $this->assertSame([5, -5, 0, 7], [ScalarCoercer::toInt('+5'), ScalarCoercer::toInt('-5'), ScalarCoercer::toInt('-0'), ScalarCoercer::toInt('007')]);
        foreach (["1\n", "1.5\n"] as $value) {
            try {
                ScalarCoercer::toFloat($value);
                $this->fail('float with a newline was accepted');
            } catch (ConversionFailed) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(ConversionFailed::class);
        ScalarCoercer::toDateTime("2026-11-06T14:30:00Z\n");
    }

    public function testEnumBackingTypeComesFromItsCases(): void
    {
        $this->assertSame(DependencyItemEnum::First, ScalarCoercer::toEnum('1', DependencyItemEnum::class));
        $this->expectException(ConversionFailed::class);
        ScalarCoercer::toEnum('x', EmptyStringEnum::class);
    }

    public function testDtoBoundaryRejectsSignedZeroGarbage(): void
    {
        $_GET = ['page' => '--0'];
        $this->assertArrayHasKey('page', $this->errors(new OptionalArgumentHandler()));
    }

    public function testPatternsAreExportedOnlyWhenPortable(): void
    {
        $this->assertSame('^[a-z]+$', new Pattern('{^[a-z]+$}')->expression());
        $this->assertSame('^[a-z]+$', new Pattern('(^[a-z]+$)')->expression());
        $this->assertSame('^[a-z]+$', new Pattern('[^[a-z]+$]')->expression());
        $this->assertSame('^[a-z]+$', new Pattern('<^[a-z]+$>')->expression());
        $this->assertSame('^[a-z]+$', new Pattern('#^[a-z]+$#uD')->expression());
        $this->assertNull(new Pattern('{^[a-z]+$}i')->expression());
        $this->assertNull(new Pattern('/^[a-z]+$/i')->expression());
        $this->assertNull(new Pattern('/^[a-z]+$/x')->expression());
    }

    public function testEveryReflectedDependencyRebuildsTheContainer(): void
    {
        $files = array_map(basename(...), DtoCompiler::compile(DependencyHandler::class)->files);
        foreach (['DependencyHandler.php', 'DependencyInput.php', 'DependencyParentInput.php', 'DependencyCaster.php', 'DependencyEnum.php', 'DependencyItemEnum.php', 'DependencyConstraint.php', 'DependencyTrait.php', 'DependencyNestedTrait.php', 'DependencyParentTrait.php'] as $file) {
            $this->assertContains($file, $files);
        }
        $this->assertSame(array_values(array_unique($files)), $files);
    }

    public function testNativeListsAndObjectsCannotComeFromAPathOrHeader(): void
    {
        $this->assertCompileFails(HeaderListHandler::class, "HeaderListInput::\$tags reads #[Header], which is one string, so it cannot be list<string>. Use a scalar type or split the string, for example #[Cast(Split::class, ',')].");
    }

    public function testQueryListIsAdvertisedInTheFormPhpReads(): void
    {
        $parameters = new ParameterBuilder()->build(new QueryListHandler());
        $this->assertSame(['ids[]', 'csv'], array_column($parameters, 'name'));
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $parameters[0]['schema']);

        $name = rawurlencode((string) $parameters[0]['name']);
        $query = sprintf('%s=3&%s=4&csv=a,b', $name, $name);
        parse_str($query, $_GET);
        $this->assertSame(['ids' => [3, 4], 'csv' => ['a', 'b']], $this->payload(new QueryListHandler()));
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
     * @return array<string, mixed>
     */
    private function payload(TypedHandler $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError(), (string) json_encode($processor->getErrors()));

        return $handler->handle($processor->getValues())->getPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function errors(TypedHandler $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertTrue($processor->isError());

        return $processor->getErrors();
    }

    private function assertCompileFails(string $handler, string $needle): void
    {
        try {
            DtoCompiler::compile($handler);
            $this->fail($handler);
        } catch (\LogicException $exception) {
            $this->assertStringContainsString($needle, $exception->getMessage(), $handler);
        }
    }
}

final readonly class Money
{
    private function __construct(public int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }
}

final readonly class MoneyCaster implements CasterInterface, OpenApiCasterInterface
{
    public function cast(mixed $value): Money
    {
        if (!is_string($value) || preg_match('/^\d+\.\d{2}\z/', $value) !== 1) {
            throw new CastException('Use a decimal amount, for example 12.50');
        }

        return Money::fromCents((int) str_replace('.', '', $value));
    }

    /**
     * @return array{type: string, example: string}
     */
    public function openApiSchema(): array
    {
        return ['type' => 'string', 'example' => '12.50'];
    }
}

final readonly class MoneyListCaster implements CasterInterface
{
    /**
     * @return list<Money>
     */
    public function cast(mixed $value): array
    {
        if (!is_array($value)) {
            throw new CastException('Use a list of amounts');
        }

        return array_values(array_map(static fn (mixed $item): Money => new MoneyCaster()->cast($item), $value));
    }
}

final readonly class MoneyInput
{
    /** @param list<Money> $prices */
    public function __construct(
        #[Body] #[Cast(MoneyCaster::class)] public Money $price,
        #[Body] #[Cast(MoneyListCaster::class)] public array $prices,
    ) {
    }
}

final class MoneyHandler extends TypedHandler
{
    public function handleDto(MoneyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['price' => $input->price->cents, 'prices' => array_map(static fn (Money $money): int => $money->cents, $input->prices)]);
    }
}

final readonly class NamedCaster implements CasterInterface
{
    public function __construct(private string $prefix = 'A', private string $suffix = 'B')
    {
    }

    public function cast(mixed $value): string
    {
        return $this->prefix . (is_string($value) ? $value : '') . $this->suffix;
    }
}

final readonly class NamedCasterInput
{
    public function __construct(
        #[Query] #[Cast(NamedCaster::class, suffix: 'END', prefix: 'START')] public string $value,
        #[Query] #[Cast(NamedCaster::class)] public string $other,
    ) {
    }
}

final class NamedCasterHandler extends TypedHandler
{
    public function handleDto(NamedCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['value' => $input->value, 'other' => $input->other]);
    }
}

final readonly class NulDateInput
{
    public function __construct(#[Body] #[Cast(DateFormat::class, 'd.m.Y')] public \DateTimeImmutable $day)
    {
    }
}

final class NulDateHandler extends TypedHandler
{
    public function handleDto(NulDateInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class TextToIntCaster implements CasterInterface
{
    public function cast(mixed $value): int
    {
        return $value === 'one' ? 1 : 0;
    }
}

final readonly class WireSchemaInput
{
    public function __construct(
        #[Query] #[Cast(UnixTimestamp::class)] public \DateTimeImmutable $at,
        #[Query] #[Cast(TextToIntCaster::class)] public int $count,
    ) {
    }
}

final class WireSchemaHandler extends TypedHandler
{
    public function handleDto(WireSchemaInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class SameAs implements ConstraintInterface
{
    public function __construct(private string $other)
    {
    }

    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        return $value === $context->value($this->other) ? null : 'Must equal ' . $this->other;
    }
}

final readonly class SiblingDefaultsInput
{
    public function __construct(
        #[Query] public string $left = 'same',
        #[Query] #[SameAs('left')] public string $right = 'same',
    ) {
    }
}

final class SiblingDefaultsHandler extends TypedHandler
{
    public function handleDto(SiblingDefaultsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadSiblingDefaultsInput
{
    public function __construct(
        #[Query] #[SameAs('left')] public string $right = 'other',
        #[Query] public string $left = 'same',
    ) {
    }
}

final class BadSiblingDefaultsHandler extends TypedHandler
{
    public function handleDto(BadSiblingDefaultsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadListDefaultInput
{
    /** @param list<int> $items */
    public function __construct(#[Query] public array $items = [1, 'not-an-int'])
    {
    }
}

final class BadListDefaultHandler extends TypedHandler
{
    public function handleDto(BadListDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class MapDefaultInput
{
    /** @param list<int> $items */
    public function __construct(#[Query] public array $items = ['a' => 1])
    {
    }
}

final class MapDefaultHandler extends TypedHandler
{
    public function handleDto(MapDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class SecretArgumentInput
{
    public function __construct(#[Query] public int $page, string $secret)
    {
        unset($secret);
    }
}

final class SecretArgumentHandler extends TypedHandler
{
    public function handleDto(SecretArgumentInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class VariadicArgumentInput
{
    public function __construct(#[Query] public int $page, string ...$rest)
    {
        unset($rest);
    }
}

final class VariadicArgumentHandler extends TypedHandler
{
    public function handleDto(VariadicArgumentInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class ReferenceArgumentInput
{
    public function __construct(#[Query] public int &$page)
    {
    }
}

final class ReferenceArgumentHandler extends TypedHandler
{
    public function handleDto(ReferenceArgumentInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class OptionalArgumentInput
{
    public function __construct(#[Query] public int $page = 1, string $mode = 'fast')
    {
        unset($mode);
    }
}

final class OptionalArgumentHandler extends TypedHandler
{
    public function handleDto(OptionalArgumentInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['page' => $input->page]);
    }
}

final readonly class HeaderListInput
{
    /** @param list<string> $tags */
    public function __construct(#[Header('X-Tags')] public array $tags = [])
    {
    }
}

final class HeaderListHandler extends TypedHandler
{
    public function handleDto(HeaderListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class QueryListInput
{
    /**
     * @param list<int> $ids
     * @param list<string> $csv
     */
    public function __construct(
        #[Query] public array $ids,
        #[Query] #[Cast(Split::class, ',')] public array $csv,
    ) {
    }
}

final class QueryListHandler extends TypedHandler
{
    public function handleDto(QueryListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['ids' => $input->ids, 'csv' => $input->csv]);
    }
}

final readonly class RequiredThenBadDefaultInput
{
    public function __construct(
        #[Query] public string $left,
        #[Query] #[SameAs('left')] public string $right = 'x',
    ) {
    }
}

final class RequiredThenBadDefaultHandler extends TypedHandler
{
    public function handleDto(RequiredThenBadDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ListDefaultsInput
{
    /**
     * @param list<float> $floats
     * @param list<bool> $flags
     * @param list<string> $names
     * @param list<Fixtures\UserStatus> $statuses
     * @param list<string> $split
     */
    public function __construct(
        #[Query] public array $floats = [1, 2.5],
        #[Query] public array $flags = [true],
        #[Query] public array $names = ['a'],
        #[Query] public array $statuses = [UserStatus::Active],
        #[Query] #[Cast(Split::class, ',')] public array $split = ['x'],
    ) {
    }
}

final class ListDefaultsHandler extends TypedHandler
{
    public function handleDto(ListDefaultsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadFloatListDefaultInput
{
    /** @param list<float> $items */
    public function __construct(#[Query] public array $items = [1.5, 'x'])
    {
    }
}

final class BadFloatListDefaultHandler extends TypedHandler
{
    public function handleDto(BadFloatListDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadBoolListDefaultInput
{
    /** @param list<bool> $items */
    public function __construct(#[Query] public array $items = [1])
    {
    }
}

final class BadBoolListDefaultHandler extends TypedHandler
{
    public function handleDto(BadBoolListDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadStringListDefaultInput
{
    /** @param list<string> $items */
    public function __construct(#[Query] public array $items = [1])
    {
    }
}

final class BadStringListDefaultHandler extends TypedHandler
{
    public function handleDto(BadStringListDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadEnumListDefaultInput
{
    /** @param list<Fixtures\UserStatus> $items */
    public function __construct(#[Query] public array $items = ['active'])
    {
    }
}

final class BadEnumListDefaultHandler extends TypedHandler
{
    public function handleDto(BadEnumListDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadCastListDefaultInput
{
    /** @param list<string> $items */
    public function __construct(#[Query] #[Cast(Split::class, ',')] public array $items = [1])
    {
    }
}

final class BadCastListDefaultHandler extends TypedHandler
{
    public function handleDto(BadCastListDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class LengthListInput
{
    /**
     * @param list<int> $ids
     * @param list<int> $min
     */
    public function __construct(
        #[Query] #[Length(min: 1, max: 2)] public array $ids,
        #[Query] #[Length(min: 1)] public array $min = [1],
    ) {
    }
}

final class LengthListHandler extends TypedHandler
{
    public function handleDto(LengthListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['ids' => $input->ids, 'min' => $input->min]);
    }
}

final readonly class StatusCaster implements CasterInterface
{
    public function cast(mixed $value): UserStatus
    {
        return UserStatus::from(is_string($value) ? $value : 'active');
    }
}

final readonly class SchemaDefaultsInput
{
    public function __construct(
        #[Query] public UserStatus $status = UserStatus::Active,
        #[Query] #[Cast(NamedCaster::class)] public string $text = 'a,b',
        #[Query] #[Cast(StatusCaster::class)] public UserStatus $casted = UserStatus::Active,
        #[Query] public Priority $priority = Priority::High,
    ) {
    }
}

final class SchemaDefaultsHandler extends TypedHandler
{
    public function handleDto(SchemaDefaultsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

enum EmptyStringEnum: string
{
}
