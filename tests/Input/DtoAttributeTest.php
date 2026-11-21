<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use MissingDto\Nope;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\Field;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\FileUpload;
use Tomaj\NetteApi\Input\OpenApiCasterInterface;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\DateHintHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\FloatRangeHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority;
use Tomaj\NetteApi\Test\Input\Fixtures\UserStatus;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\ConstraintInterface;
use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

final class DtoAttributeTest extends TestCase
{
    public function testCompileFailuresNameTheExactProblem(): void
    {
        $this->assertCompileFails(MissingDtoHandler::class, "DTO 'MissingDto\\Nope' does not exist");
        $this->assertCompileFails(UnionReturnHandler::class, 'must return');
        try {
            DtoCompiler::compile(CycleLeftHandler::class);
            $this->fail('cycle');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString(CycleRight::class, $exception->getMessage());
            $this->assertStringContainsString(CycleLeft::class . ' -> ' . CycleRight::class . ' -> ' . CycleLeft::class, $exception->getMessage());
        }
        $this->assertCompileFails(ParentQueryListHandler::class, 'Nested properties are part of the body');
        $this->assertCompileFails(UnknownListItemHandler::class, 'documents list<MissingDto\\Nope>,');
        $this->assertCompileFails(IntCasterHandler::class, 'must return string');
        $this->assertCompileFails(AbstractCasterHandler::class, 'must be a concrete class');
        $this->assertCompileFails(ExtraSchemaHandler::class, 'Return only type, format and example');
        $this->assertCompileFails(NullableReturnCasterHandler::class, '(string)');
        $this->assertCompileFails(ListDocCasterHandler::class, '@return list<string>');
    }

    public function testAcceptedShapes(): void
    {
        $nullable = DtoCompiler::compile(NullableIntHandler::class);
        $this->assertTrue($nullable->fields[0]->nullable);
        $child = DtoCompiler::compile(ChildPinHandler::class);
        $this->assertSame(PinBase::class, $child->fields[0]->typeName);
        $this->assertSame('string', DtoCompiler::compile(StringChildHandler::class)->fields[0]->typeName);
        $this->assertSame('name', DtoCompiler::compile(BoomThenQueryHandler::class)->fields[0]->externalName);
        $varList = DtoCompiler::compile(VarListHandler::class)->fields[0];
        $this->assertSame('string', $varList->listItem);
        $priorities = DtoCompiler::compile(PriorityListHandler::class)->fields[0]->item;
        $this->assertInstanceOf(Field::class, $priorities);
        $this->assertSame(Priority::class, $priorities->enumClass);
        $this->assertTrue($priorities->enumInt);
        $this->assertSame([1, 2], $priorities->enumValues);
        $float = DtoCompiler::compile(FloatRangeHandler::class);
        $this->assertInstanceOf(Range::class, $float->fields[0]->constraints[0]);
        $this->assertInstanceOf(Length::class, DtoCompiler::compile(StringLengthHandler::class)->fields[0]->constraints[0]);
        $this->assertInstanceOf(Pattern::class, DtoCompiler::compile(PatternHandler::class)->fields[0]->constraints[0]);
        $this->assertInstanceOf(Email::class, DtoCompiler::compile(EmailHandler::class)->fields[0]->constraints[0]);
        $this->assertCompileFails(NestedFileHandler::class, 'NestedUploadInput::$avatar uses #[File]. Uploads are read only on the root DTO');
        $hint = DtoCompiler::compile(DateHintHandler::class)->fields[0]->casterSchema;
        $this->assertIsArray($hint);
        $this->assertSame('string', $hint['type']);
        $this->assertArrayNotHasKey('format', $hint, 'd.m.Y is not the OpenAPI full-date');
        $emptyHint = DtoCompiler::compile(EmptyHintHandler::class)->fields[0]->casterSchema;
        $this->assertSame(['type' => 'string'], $emptyHint);
        $plain = DtoCompiler::compile(PlainCasterHandler::class)->fields[0];
        $this->assertSame(['type' => 'string'], $plain->casterSchema, 'without a hint the wire value is a string');
    }

    public function testWrongConstraintHintsAndFileListTypes(): void
    {
        $this->assertCompileFails(BadFileListHandler::class, 'list<string>');
        $this->assertCompileFails(PatternOnIntHandler::class, 'Use it on a string.');
        $this->assertCompileFails(EmailOnIntHandler::class, 'Use it on a string.');
        $this->assertCompileFails(LengthOnIntHandler::class, 'Use it on string (characters) or a list (items).');
        $this->assertCompileFails(RangeOnStringPinHandler::class, 'Use it on int or float.');
        DtoCompiler::compile(NullRangeHandler::class);
        $statuses = DtoCompiler::compile(StatusListHandler::class)->fields[0]->item;
        $this->assertInstanceOf(Field::class, $statuses);
        $this->assertFalse($statuses->enumInt);
        $schema = DtoCompiler::compile(FormatOnlyHandler::class)->fields[0]->casterSchema;
        $this->assertIsArray($schema);
        $this->assertSame('string', $schema['type']);
        $this->assertSame('date', $schema['format']);
        DtoCompiler::compile(GoodListCasterHandler::class);
        DtoCompiler::compile(SeesItselfHandler::class);
        $this->assertCompileFails(FileListHandler::class, 'Uploads are read only on the root DTO');
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

final class MissingDtoHandler extends TypedHandler
{
    public function handleDto(Nope $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class UnionPlainInput
{
    public function __construct(#[Query] public string $name)
    {
    }
}

final class UnionReturnHandler extends TypedHandler
{
    public function handleDto(UnionPlainInput $input): ResponseInterface|\stdClass
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class CycleLeft
{
    public function __construct(#[Body] public CycleRight $right)
    {
    }
}

final readonly class CycleRight
{
    public function __construct(#[Body] public CycleLeft $left)
    {
    }
}

final class CycleLeftHandler extends TypedHandler
{
    public function handleDto(CycleLeft $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NullableIntInput
{
    public function __construct(#[Query] public int|null $page = null)
    {
    }
}

final class NullableIntHandler extends TypedHandler
{
    public function handleDto(NullableIntInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

readonly class PinBase
{
    public function __construct(#[Body] private string $name)
    {
    }
}

final readonly class ChildPin extends PinBase
{
    public function __construct(#[Body] public parent $item)
    {
        parent::__construct('x');
    }
}

final readonly class StringChildInput extends PinBase
{
    public function __construct(#[Query] public string $name)
    {
        parent::__construct('x');
    }
}

final class StringChildHandler extends TypedHandler
{
    public function handleDto(StringChildInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class ChildPinHandler extends TypedHandler
{
    public function handleDto(ChildPin $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class BoomAttribute
{
    public function __construct()
    {
        throw new \RuntimeException('boom');
    }
}

final readonly class BoomThenQueryInput
{
    public function __construct(
        #[BoomAttribute]
        #[Query]
        public string $name,
    ) {
    }
}

final class BoomThenQueryHandler extends TypedHandler
{
    public function handleDto(BoomThenQueryInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class VarListInput
{
    public function __construct(
        #[Query]
        /** @var list<string> */
        public array $columns,
    ) {
    }
}

final class VarListHandler extends TypedHandler
{
    public function handleDto(VarListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class PriorityListInput
{
    /** @param list<Priority> $priorities */
    public function __construct(#[Query] public array $priorities)
    {
    }
}

final class PriorityListHandler extends TypedHandler
{
    public function handleDto(PriorityListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class QueryItemInput
{
    public function __construct(#[Query] public string $name)
    {
    }
}

final readonly class ParentQueryListInput
{
    /** @param list<QueryItemInput> $items */
    public function __construct(#[Body] public array $items)
    {
    }
}

final class ParentQueryListHandler extends TypedHandler
{
    public function handleDto(ParentQueryListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class UnknownListItemInput
{
    /** @param list<MissingDto\Nope> $items */
    public function __construct(#[Query] public array $items)
    {
    }
}

final class UnknownListItemHandler extends TypedHandler
{
    public function handleDto(UnknownListItemInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class StringLengthInput
{
    public function __construct(#[Query] #[Length(min: 1, max: 3)] public string $name = 'ab')
    {
    }
}

final class StringLengthHandler extends TypedHandler
{
    public function handleDto(StringLengthInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class PatternPinInput
{
    public function __construct(#[Query] #[Pattern('/^a+$/')] public string $name = 'a')
    {
    }
}

final class PatternHandler extends TypedHandler
{
    public function handleDto(PatternPinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class EmailPinInput
{
    public function __construct(#[Query] #[Email] public string $email = 'a@b.c')
    {
    }
}

final class EmailHandler extends TypedHandler
{
    public function handleDto(EmailPinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class IntForString implements CasterInterface
{
    public function cast(mixed $value): int
    {
        return 1;
    }
}

final readonly class IntCasterInput
{
    public function __construct(#[Query] #[Cast(IntForString::class)] public string $name)
    {
    }
}

final class IntCasterHandler extends TypedHandler
{
    public function handleDto(IntCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

abstract class AbstractCaster implements CasterInterface
{
    public function cast(mixed $value): string
    {
        return '';
    }
}

final readonly class AbstractCasterInput
{
    public function __construct(#[Query] #[Cast(AbstractCaster::class)] public string $name)
    {
    }
}

final class AbstractCasterHandler extends TypedHandler
{
    public function handleDto(AbstractCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class ExtraSchemaCaster implements CasterInterface, OpenApiCasterInterface
{
    public function cast(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    public function openApiSchema(): array
    {
        return ['type' => 'string', 'minLength' => 1];
    }
}

final readonly class ExtraSchemaInput
{
    public function __construct(#[Query] #[Cast(ExtraSchemaCaster::class)] public string $name)
    {
    }
}

final class ExtraSchemaHandler extends TypedHandler
{
    public function handleDto(ExtraSchemaInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class NullableReturnCaster implements CasterInterface
{
    public function cast(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}

final readonly class NullableReturnCasterInput
{
    public function __construct(#[Query] #[Cast(NullableReturnCaster::class)] public string $name)
    {
    }
}

final class NullableReturnCasterHandler extends TypedHandler
{
    public function handleDto(NullableReturnCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class ListDocCaster implements CasterInterface
{
    public function cast(mixed $value): array
    {
        return [];
    }
}

final readonly class ListDocCasterInput
{
    /** @param list<string> $columns */
    public function __construct(#[Query] #[Cast(ListDocCaster::class)] public array $columns)
    {
    }
}

final class ListDocCasterHandler extends TypedHandler
{
    public function handleDto(ListDocCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class EmptyHintCaster implements CasterInterface, OpenApiCasterInterface
{
    public function cast(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    public function openApiSchema(): array
    {
        return [];
    }
}

final readonly class EmptyHintInput
{
    public function __construct(#[Query] #[Cast(EmptyHintCaster::class)] public string $name = 'a')
    {
    }
}

final class EmptyHintHandler extends TypedHandler
{
    public function handleDto(EmptyHintInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class PlainStringCaster implements CasterInterface
{
    public function cast(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}

final readonly class PlainCasterInput
{
    public function __construct(#[Query] #[Cast(PlainStringCaster::class)] public string $name = 'a')
    {
    }
}

final class PlainCasterHandler extends TypedHandler
{
    public function handleDto(PlainCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NestedUploadInput
{
    public function __construct(#[File] public FileUpload $avatar)
    {
    }
}

final readonly class NestedFileInput
{
    public function __construct(#[Body] public NestedUploadInput $upload)
    {
    }
}

final class NestedFileHandler extends TypedHandler
{
    public function handleDto(NestedFileInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class RangeOnStringPinInput
{
    public function __construct(#[Query] #[Range(min: 1)] public string $name)
    {
    }
}

final class RangeOnStringPinHandler extends TypedHandler
{
    public function handleDto(RangeOnStringPinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadFileListInput
{
    /** @param list<string> $files */
    public function __construct(#[File] public array $files)
    {
    }
}

final class BadFileListHandler extends TypedHandler
{
    public function handleDto(BadFileListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class PatternOnIntInput
{
    public function __construct(#[Query] #[Pattern('/^1$/')] public int $page)
    {
    }
}

final class PatternOnIntHandler extends TypedHandler
{
    public function handleDto(PatternOnIntInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class EmailOnIntInput
{
    public function __construct(#[Query] #[Email] public int $page)
    {
    }
}

final class EmailOnIntHandler extends TypedHandler
{
    public function handleDto(EmailOnIntInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class LengthOnIntInput
{
    public function __construct(#[Query] #[Length(min: 1)] public int $page)
    {
    }
}

final class LengthOnIntHandler extends TypedHandler
{
    public function handleDto(LengthOnIntInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NullRangeInput
{
    public function __construct(#[Query] #[Range(min: 1)] public ?int $page = null)
    {
    }
}

final class NullRangeHandler extends TypedHandler
{
    public function handleDto(NullRangeInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class StatusListInput
{
    /** @param list<UserStatus> $statuses */
    public function __construct(#[Query] public array $statuses)
    {
    }
}

final class StatusListHandler extends TypedHandler
{
    public function handleDto(StatusListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class FormatOnlyCaster implements CasterInterface, OpenApiCasterInterface
{
    public function cast(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    public function openApiSchema(): array
    {
        return ['format' => 'date'];
    }
}

final readonly class FormatOnlyInput
{
    public function __construct(#[Query] #[Cast(FormatOnlyCaster::class)] public string $name = 'a')
    {
    }
}

final class FormatOnlyHandler extends TypedHandler
{
    public function handleDto(FormatOnlyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class GoodListCaster implements CasterInterface
{
    /** @return list<string> */
    public function cast(mixed $value): array
    {
        return [];
    }
}

final readonly class GoodListCasterInput
{
    /** @param list<string> $columns */
    public function __construct(#[Query] #[Cast(GoodListCaster::class)] public array $columns = [])
    {
    }
}

final class GoodListCasterHandler extends TypedHandler
{
    public function handleDto(GoodListCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SeesItself implements ConstraintInterface
{
    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        return $context->value('page') === 1 ? null : 'sibling missing';
    }
}

final readonly class SeesItselfInput
{
    public function __construct(#[Query] #[SeesItself] public int $page = 1)
    {
    }
}

final class SeesItselfHandler extends TypedHandler
{
    public function handleDto(SeesItselfInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class FileListInput
{
    /** @param list<NestedUploadInput> $uploads */
    public function __construct(#[Body] public array $uploads)
    {
    }
}

final class FileListHandler extends TypedHandler
{
    public function handleDto(FileListInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
