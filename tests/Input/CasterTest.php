<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\CastException;
use Tomaj\NetteApi\Input\DateFormat;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Input\UnixTimestamp;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\DtoMetadata;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
use Tomaj\NetteApi\Input\OpenApiCasterInterface;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\SchemaBuilder;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\NamedCaster;
use Tomaj\NetteApi\Test\Input\Fixtures\SplitHandler;

final class CasterTest extends TestCase
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

    public function testDateFormat(): void
    {
        $caster = new DateFormat('d.m.Y');
        $date = $caster->cast('24.12.2026');
        $this->assertSame('2026-12-24 00:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame(['type' => 'string', 'example' => '24.12.2026'], $caster->openApiSchema(), 'format: date means Y-m-d only');
        $this->assertSame(['type' => 'string', 'format' => 'date', 'example' => '2026-12-24'], new DateFormat('Y-m-d')->openApiSchema());

        foreach (['31.02.2026', '24.12.2026 ', '2026-12-24', 1, ''] as $value) {
            try {
                $caster->cast($value);
                $this->fail('accepted ' . var_export($value, true));
            } catch (CastException $exception) {
                $this->assertSame('Date must match d.m.Y, for example 24.12.2026', $exception->getMessage());
            }
        }
    }

    public function testDateFormatRejectsAnEmptyFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DateFormat needs a PHP date format, for example #[Cast(DateFormat::class, 'd.m.Y')].");
        new DateFormat('');
    }

    public function testDateFormatRejectsAFormatThatCannotReadItsOwnDates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("DateFormat 'c' cannot read back the dates it writes (2026-12-24T00:00:00+00:00), so no value would pass. Use a format such as 'Y-m-d' or 'd.m.Y'.");
        new DateFormat('c');
    }

    public function testUnixTimestamp(): void
    {
        $caster = new UnixTimestamp();
        $this->assertSame('2026-12-24T00:00:00+00:00', $caster->cast('1798070400')->format('c'));
        $this->assertSame('2026-12-24T00:00:00+00:00', $caster->cast(1798070400)->format('c'));
        $this->assertSame(['type' => 'integer', 'example' => 1798185600], $caster->openApiSchema());

        $this->expectException(CastException::class);
        $caster->cast('1798070400abc');
    }

    public function testSplitKeepsEmptyPieces(): void
    {
        $caster = new Split(',');
        $this->assertSame(['red', 'green', 'blue'], $caster->cast('red,green,blue'));
        $this->assertSame([''], $caster->cast(''));
        $this->assertSame(['a', '', 'b'], $caster->cast('a,,b'));
        $this->assertSame(['type' => 'string', 'example' => 'red,green,blue'], $caster->openApiSchema());
        $this->assertSame('red|green|blue', new Split('|')->openApiSchema()['example']);
        try {
            $caster->cast("a,\xff");
            $this->fail('invalid UTF-8 was split');
        } catch (CastException $exception) {
            $this->assertSame('Value must be valid UTF-8', $exception->getMessage());
        }

        $this->expectException(CastException::class);
        $caster->cast(['red']);
    }

    public function testSplitRejectsAnEmptySeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Split('');
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
        $imported = unserialize(serialize($compiled));
        $this->assertInstanceOf(DtoMetadata::class, $imported);
        $this->assertSame($compiled->fields[0]->casterSchema, $imported->fields[0]->casterSchema);
        $this->assertSame('integer', $imported->fields[0]->casterSchema['type'] ?? null);

        DtoMetadataRegistry::install([NamedCasterHandler::class => serialize(DtoCompiler::compile(NamedCasterHandler::class))]);
        $this->assertSame(['value' => 'STARTXEND', 'other' => 'AYB'], $this->payload(new NamedCasterHandler()));
    }

    public function testOneCasterInstanceServesEveryValueOfItsField(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"items":[{"name":"a"},{"name":"b"},{"name":"c"}],"title":"x"}');
        $handler = new CountedHandler();
        // compiling checks the caster arguments by constructing it
        DtoMetadataRegistry::forHandler(CountedHandler::class);
        CountingCaster::$instances = 0;

        $this->assertSame(['names' => ['a!', 'b!', 'c!'], 'title' => 'x?'], $this->payload($handler));
        $this->assertSame(2, CountingCaster::$instances, 'one per field, not one per list item');
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

    public function testSplitPropertyIsOneStringInputWithoutAnArrayDefault(): void
    {
        $param = new SplitHandler()->params()[0];
        $this->assertFalse($param->isMulti());
        $this->assertNull($param->getDefault());
        $this->assertSame(['type' => 'string', 'example' => 'red,green,blue'], $param->getOpenApiSchema());

        $parameters = new ParameterBuilder()->build(new SplitHandler());
        $this->assertSame(['tags', 'label'], array_column($parameters, 'name'));
        $this->assertSame('plain', $parameters[1]['schema']['default']);
        $this->assertSame('plain', new SplitHandler()->params()[1]->getDefault());

        $_GET = ['tags' => 'a,b'];
        $this->assertSame(['tags' => ['a', 'b']], $this->payload(new SplitHandler()));
        RequestInput::reset();
        $_GET = [];
        $this->assertSame(['tags' => []], $this->payload(new SplitHandler()));
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

final class CountingCaster implements CasterInterface
{
    public static int $instances = 0;

    public function __construct(private readonly string $suffix)
    {
        self::$instances++;
    }

    public function cast(mixed $value): string
    {
        return (is_string($value) ? $value : '') . $this->suffix;
    }
}

final readonly class CountedItem
{
    public function __construct(#[Body] #[Cast(CountingCaster::class, '!')] public string $name)
    {
    }
}

final readonly class CountedInput
{
    /**
     * @param list<CountedItem> $items
     */
    public function __construct(
        #[Body] public array $items,
        #[Body] #[Cast(CountingCaster::class, '?')] public string $title,
    ) {
    }
}

final class CountedHandler extends TypedHandler
{
    public function handleDto(CountedInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['names' => array_map(static fn (CountedItem $item): string => $item->name, $input->items), 'title' => $input->title]);
    }
}
