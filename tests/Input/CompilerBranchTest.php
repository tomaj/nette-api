<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Input\Field;
use Tomaj\NetteApi\Input\DtoMetadata;
use Tomaj\NetteApi\DI\NetteApiExtension;
use MissingDto\Nope;
use Tomaj\NetteApi\Input\Path;
use Nette\DI\Compiler;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\DateFormat;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
use Tomaj\NetteApi\Input\EndpointTemplate;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\FileUpload;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\OpenApiCasterInterface;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\SchemaBuilder;
use Tomaj\NetteApi\Input\ValueExport;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\DI\ContainerFactory;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\UserStatus;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\ConstraintInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority;
use Tomaj\NetteApi\Test\Input\Fixtures\Separate\NoteHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Separate\NoteInput;
use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

final class CompilerBranchTest extends TestCase
{
    protected function setUp(): void
    {
        DtoMetadataRegistry::reset();
    }

    public function testQueryBeforeBodyStillReadsTheBody(): void
    {
        $metadata = DtoCompiler::compile(QueryThenBodyHandler::class);
        $this->assertTrue($metadata->readsBody);
        $this->assertTrue($metadata->bodyRequired);
        $this->assertSame('email', $metadata->firstBody);
    }

    public function testHandlerAndDtoFilesAreBothRecorded(): void
    {
        $files = array_map(realpath(...), DtoCompiler::compile(NoteHandler::class)->files);
        $this->assertContains(realpath(new \ReflectionClass(NoteHandler::class)->getFileName() ?: ''), $files);
        $this->assertContains(realpath(new \ReflectionClass(NoteInput::class)->getFileName() ?: ''), $files);
    }

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
        $this->assertTrue($varList->listItemBuiltin);
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
        $nestedFile = DtoCompiler::compile(NestedFileHandler::class);
        $this->assertSame([MediaTypes::JSON, MediaTypes::MULTIPART], $nestedFile->contentTypes);
        $hint = DtoCompiler::compile(DateHintHandler::class)->fields[0]->casterSchema;
        $this->assertIsArray($hint);
        $this->assertSame('string', $hint['type']);
        $this->assertSame('date', $hint['format']);
        $emptyHint = DtoCompiler::compile(EmptyHintHandler::class)->fields[0]->casterSchema;
        $this->assertSame(['type' => 'string'], $emptyHint);
        $plain = DtoCompiler::compile(PlainCasterHandler::class)->fields[0];
        $this->assertNull($plain->casterSchema);
    }

    public function testSchemaTypes(): void
    {
        $float = SchemaBuilder::build(DtoCompiler::compile(FloatRangeHandler::class)->fields[0]);
        $this->assertSame('number', $float['type']);
        $bool = SchemaBuilder::build(DtoCompiler::compile(BoolHandler::class)->fields[0]);
        $this->assertSame('boolean', $bool['type']);
        $file = SchemaBuilder::build(DtoCompiler::compile(NestedFileHandler::class)->fields[0]->children[0]);
        $this->assertSame(['type' => 'string', 'format' => 'binary'], $file);
        $date = SchemaBuilder::build(DtoCompiler::compile(DateHintHandler::class)->fields[0]);
        $this->assertSame('string', $date['type']);
        $this->assertSame('date', $date['format']);
    }

    public function testImportDropsNonStringsAndReindexes(): void
    {
        $export = DtoCompiler::compile(ListUsersHandler::class)->export();
        $export['contentTypes'] = [0 => MediaTypes::JSON, 2 => MediaTypes::FORM];
        $export['files'] = [0 => '/tmp/a.php', 1 => 5, 2 => '/tmp/b.php'];
        $imported = DtoMetadata::import($export);
        $this->assertSame([MediaTypes::JSON, MediaTypes::FORM], $imported->contentTypes);
        $this->assertTrue(array_is_list($imported->contentTypes));
        $this->assertSame(['/tmp/a.php', '/tmp/b.php'], $imported->files);
    }

    public function testEnumRoundTripRejectsAForeignMarker(): void
    {
        $restored = ValueExport::import(['__netteApi' => 'enum', 'class' => Priority::class, 'value' => 2]);
        $this->assertSame(Priority::High, $restored);
        $notAnEnum = ValueExport::import(['__netteApi' => 'other', 'class' => Priority::class, 'value' => 2]);
        $this->assertIsArray($notAnEnum);
    }

    public function testEndpointMessagesUseTheRealUrl(): void
    {
        $this->assertSame('v1/users', EndpointTemplate::normalize('/api/v1/users/', '1'));
        foreach (['v1/x{id}', 'v1/{id}x'] as $url) {
            try {
                EndpointTemplate::assert($url, [], 'Handler');
                $this->fail($url);
            } catch (\LogicException $exception) {
                $this->assertStringContainsString($url, $exception->getMessage());
            }
        }
        try {
            EndpointTemplate::assert('v1/users/{id}/', [], 'Handler');
            $this->fail('slash');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString("endpoint URL 'v1/users/{id}'", $exception->getMessage());
            $this->assertStringNotContainsString("endpoint URL 'v1/users/{id}/'", $exception->getMessage());
        }
        try {
            EndpointTemplate::assert('v1/{id}/x/{id}/y/{id}/', ['id'], 'Handler');
            $this->fail('duplicate');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('repeats {id}.', $exception->getMessage());
            $this->assertStringContainsString("endpoint URL 'v1/{id}/x/{id}/y/{id}'", $exception->getMessage());
        }
        try {
            EndpointTemplate::assert('v1/users/{id-1}/', [], 'Handler');
            $this->fail('malformed');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString("endpoint URL 'v1/users/{id-1}'", $exception->getMessage());
            $this->assertStringNotContainsString('{id-1}/', $exception->getMessage());
        }
        EndpointTemplate::assertDisjoint(['GET' => ['v1/static', 'v1/users/{id}', 'v1/orders/{id}']]);
        try {
            EndpointTemplate::assertDisjoint(['GET' => ['v1/users/{id}/', 'v1/users/{slug}/']]);
            $this->fail('overlap');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString("endpoints 'v1/users/{id}' and 'v1/users/{slug}'", $exception->getMessage());
        }
    }

    public function testContainerKeepsTypedHandlersAfterAPlainOneAndRejectsOverlap(): void
    {
        $container = ContainerFactory::create(<<<'NEON'
services:
    listUsers: Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler
api:
    endpoints:
        - {method: GET, version: 1, package: ping, handler: Tomaj\NetteApi\Handlers\AlwaysOkHandler}
        - {method: GET, version: 1, package: people, action: list, handler: @listUsers}
        - {method: POST, version: 1, package: notes, handler: \Tomaj\NetteApi\Test\Input\Fixtures\Separate\NoteHandler()}
NEON);
        $container->initialize();
        $this->assertTrue(DtoMetadataRegistry::installs(ListUsersHandler::class));
        $this->assertTrue(DtoMetadataRegistry::installs(NoteHandler::class));
        $this->assertInstanceOf(AlwaysOkHandler::class, $container->getByType(AlwaysOkHandler::class));

        $compiler = new Compiler();
        $compiler->addExtension('api', new NetteApiExtension());
        $compiler->addConfig([
            'api' => [
                'endpoints' => [[
                    'method' => 'POST',
                    'version' => 1,
                    'package' => 'notes',
                    'handler' => NoteHandler::class,
                ]],
            ],
        ]);
        $compiler->compile();
        $this->assertStringContainsString('NoteInput.php', serialize($compiler->exportDependencies()));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('can match the same request');
        ContainerFactory::create(<<<'NEON'
api:
    endpoints:
        - {method: GET, version: 1, package: users, action: '{id}', handler: Tomaj\NetteApi\Test\Input\IdOnlyHandler}
        - {method: GET, version: 1, package: users, action: '{slug}', handler: Tomaj\NetteApi\Test\Input\SlugOnlyHandler}
NEON);
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
        $this->assertSame([MediaTypes::JSON, MediaTypes::MULTIPART], DtoCompiler::compile(FileListHandler::class)->contentTypes);
    }

    public function testConstraintAndBodyErrorsStayOnTheirFields(): void
    {
        $_GET = [];
        RequestInput::reset();
        $ok = new ListUsersHandler()->params()[0]->validate();
        $this->assertInstanceOf(ValidationResult::class, $ok);

        RequestInput::reset();
        $_GET = ['page' => '1a', 'limit' => '0'];
        $errors = $this->errors(new ListUsersHandler());
        $this->assertSame(['Value must be an integer'], $errors['page']);
        $this->assertSame(['Range must be between 1 and 100'], $errors['limit']);

        RequestInput::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('{"customerEmail":');
        $bodyErrors = [];
        foreach (new CreateOrderHandler()->params() as $param) {
            $result = $param->validate();
            if (!$result->isOk()) {
                $bodyErrors[$param->getKey()] = $result->getErrors();
            }
        }
        $this->assertArrayHasKey('body', $bodyErrors['customerEmail']);
        $this->assertArrayNotHasKey('body', $bodyErrors['address'] ?? []);

        RequestInput::reset();
        RequestInput::reset();
        $_GET = ['page' => '1a'];
        $both = $this->errors(new QueryThenBodyHandler());
        $this->assertArrayHasKey('page', $both);
        $this->assertArrayHasKey('email', $both);

        RequestInput::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];
        RequestInput::setRawBody('');
        $emptyBody = $this->errors(new CreateOrderHandler());
        $this->assertArrayHasKey('customerEmail', $emptyBody);
        $this->assertArrayNotHasKey('body', $emptyBody);

        RequestInput::reset();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['customerEmail' => 'ada@example.com', 'address' => ['street' => 'Main 1', 'postalCode' => '81101'], 'items' => [['sku' => 'A', 'quantity' => 1]]];
        RequestInput::setRawBody('');
        $this->assertSame('ada@example.com', $this->payload(new CreateOrderHandler())['email']);

        $_GET = ['name' => 'ab'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['CONTENT_TYPE']);
        $length = $this->errors(new TwoConstraintHandler());
        $this->assertCount(2, $length['name']);
    }

    public function testGeneratedParamsExposeDefaultEnumAndExamples(): void
    {
        $params = new ListUsersHandler()->params();
        $this->assertSame(1, $params[0]->getDefault());
        $values = $params[2]->getAvailableValues();
        $this->assertIsArray($values);
        $this->assertContains('active', $values);
        $this->assertContains('disabled', $values);
        $this->assertTrue($params[0]->validate()->isOk());
        $examples = new TwoExampleParamHandler()->params()[0]->getExamples();
        $this->assertCount(2, $examples);
        $this->assertArrayHasKey('one', $examples);
        $this->assertArrayHasKey('two', $examples);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(object $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError(), (string) json_encode($processor->getErrors()));

        return $handler->handle($processor->getValues())->getPayload();
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(object $handler): array
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

final readonly class QueryThenBodyInput
{
    public function __construct(
        #[Query] public int $page,
        #[Body] public string $email,
    ) {
    }
}

final class QueryThenBodyHandler extends TypedHandler
{
    public function handleDto(QueryThenBodyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
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

final readonly class FloatRangeInput
{
    public function __construct(#[Query] #[Range(min: 0.5, max: 2.5)] public float $ratio = 1.0)
    {
    }
}

final class FloatRangeHandler extends TypedHandler
{
    public function handleDto(FloatRangeInput $input): ResponseInterface
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

final readonly class BoolPinInput
{
    public function __construct(#[Query] public bool $flag = false)
    {
    }
}

final class BoolHandler extends TypedHandler
{
    public function handleDto(BoolPinInput $input): ResponseInterface
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

final readonly class DateHintInput
{
    public function __construct(#[Query] #[Cast(DateFormat::class, 'd.m.Y')] public \DateTimeImmutable $day)
    {
    }
}

final class DateHintHandler extends TypedHandler
{
    public function handleDto(DateHintInput $input): ResponseInterface
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

final readonly class TwoExampleParamInput
{
    public function __construct(#[Query(examples: ['one' => 1, 'two' => 2])] public int $page = 1)
    {
    }
}

final class TwoExampleParamHandler extends TypedHandler
{
    public function handleDto(TwoExampleParamInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class IdOnlyInput
{
    public function __construct(#[Path] public string $id)
    {
    }
}

final class IdOnlyHandler extends TypedHandler
{
    public function handleDto(IdOnlyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class SlugOnlyInput
{
    public function __construct(#[Path] public string $slug)
    {
    }
}

final class SlugOnlyHandler extends TypedHandler
{
    public function handleDto(SlugOnlyInput $input): ResponseInterface
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

final readonly class TwoConstraintInput
{
    public function __construct(
        #[Query]
        #[Length(min: 3)]
        #[Pattern('/^z+$/')]
        public string $name,
    ) {
    }
}

final class TwoConstraintHandler extends TypedHandler
{
    public function handleDto(TwoConstraintInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
