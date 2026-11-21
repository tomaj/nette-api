<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Input\FileUpload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\Consumes;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\Range;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Test\Input\Fixtures\AddressInput;
use Tomaj\NetteApi\Test\Input\Fixtures\AvatarHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\DescribedHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority;
use Tomaj\NetteApi\Test\Input\Fixtures\TwoExamplesHandler;

final class DtoCompilerTest extends TestCase
{
    /**
     * @return \Iterator<string, array{class-string, string}>
     */
    public static function invalidHandlers(): \Iterator
    {
        yield 'missing method' => [MissingMethodHandler::class, 'must implement public function handleDto'];
        yield 'two parameters' => [TwoParameterHandler::class, 'exactly one parameter'];
        yield 'nullable parameter' => [NullableDtoHandler::class, 'non-nullable DTO'];
        yield 'no return type' => [NoReturnHandler::class, 'must return'];
        yield 'not a dto class' => [ScalarHandler::class, 'type-hinted with the DTO class'];
        yield 'no source' => [NoSourceHandler::class, 'no source attribute'];
        yield 'two sources' => [TwoSourceHandler::class, 'more than one source'];
        yield 'union' => [UnionHandler::class, 'not a union'];
        yield 'mixed' => [MixedHandler::class, 'no contract to validate'];
        yield 'untyped array' => [UntypedArrayHandler::class, 'without an item type'];
        yield 'associative array' => [AssociativeArrayHandler::class, 'not a list'];
        yield 'nullable path' => [NullablePathHandler::class, 'cannot be nullable'];
        yield 'path default' => [DefaultPathHandler::class, 'cannot have a default'];
        yield 'file type' => [BadFileHandler::class, 'Use FileUpload'];
        yield 'nested query' => [NestedQueryHandler::class, 'Nested properties are part of the body'];
        yield 'bad name' => [BadNameHandler::class, 'Dots and brackets'];
        yield 'duplicate name' => [DuplicateNameHandler::class, 'both use the external name'];
        yield 'range on string' => [RangeOnStringHandler::class, 'does not apply to string'];
        yield 'bad default' => [BadDefaultHandler::class, 'default does not satisfy'];
        yield 'unknown caster' => [UnknownCasterHandler::class, 'does not exist'];
        yield 'json only file' => [JsonFileHandler::class, 'multipart/form-data'];
        yield 'unknown consumes' => [CsvConsumesHandler::class, 'cannot read'];
        yield 'duplicate consumes' => [DuplicateConsumesHandler::class, 'twice'];
        yield 'not readonly' => [WritableHandler::class, 'must be readonly'];
        yield 'empty dto' => [EmptyDtoHandler::class, 'needs a constructor'];
        yield 'empty examples' => [ListExamplesHandler::class, 'must be a map'];
        yield 'cycle' => [CycleHandler::class, 'refers to itself'];
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

    public function testMissingClass(): void
    {
        $this->expectException(\LogicException::class);
        DtoCompiler::compile('Missing\\Handler');
    }

    public function testMissingHandlerNamesTheClass(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Typed handler 'Missing\\Handler' does not exist. Check the class name and the autoload section of composer.json.");
        DtoCompiler::compile('Missing\\Handler');
    }

    public function testCycleNamesEveryStep(): void
    {
        try {
            DtoCompiler::compile(CyclePinHandler::class);
            $this->fail('cycle');
        } catch (\LogicException $exception) {
            $this->assertSame(
                sprintf(
                    'DTO %s refers to itself (%s -> %s). Nested DTOs cannot form a cycle; keep the nesting one way.',
                    CyclePinInput::class,
                    CyclePinInput::class,
                    CyclePinInput::class,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function testDeclarationsThatCannotBeMapped(): void
    {
        $cases = [
            PrivateHandler::class => 'must be a public instance method',
            StaticHandler::class => 'must be a public instance method',
            StdClassReturnHandler::class => 'must return',
            AbstractDtoHandler::class => 'cannot be an input DTO',
            EnumDtoHandler::class => 'cannot be an input DTO',
            InterfaceDtoHandler::class => 'cannot be an input DTO',
            IterableHandler::class => 'has type iterable',
            FileOnQueryHandler::class => 'needs #[File]',
            BareFileUploadHandler::class => 'needs #[File]',
        ];
        foreach ($cases as $handler => $needle) {
            try {
                DtoCompiler::compile($handler);
                $this->fail($handler);
            } catch (\LogicException $exception) {
                $this->assertStringContainsString($needle, $exception->getMessage(), $handler);
            }
        }

        $examples = DtoCompiler::compile(TwoExamplesHandler::class)->fields[0]->examples;
        $this->assertSame(['one' => 1, 'two' => 2], $examples);

        $twin = DtoCompiler::compile(TwinHandler::class);
        $this->assertSame('left', $twin->firstBody);
        $this->assertTrue($twin->readsBody);
        $address = realpath(new \ReflectionClass(AddressInput::class)->getFileName() ?: '');
        $twinFiles = DtoCompiler::compileWithFiles(TwinHandler::class)['files'];
        $this->assertCount(1, array_filter($twinFiles, static fn (string $file): bool => realpath($file) === $address));
        $this->assertSame(['id', 'slug'], DtoCompiler::compile(TwoPathsHandler::class)->pathNames());
        $this->assertSame(
            [MediaTypes::JSON, MediaTypes::MULTIPART],
            DtoCompiler::compile(AvatarHandler::class)->contentTypes,
        );
        $described = DtoCompiler::compile(DescribedHandler::class);
        $this->assertFalse($described->bodyRequired);
        $this->assertTrue($described->readsBody);
        $this->assertSame([MediaTypes::JSON], $described->contentTypes);
    }

    public function testHeaderNamesCollideCaseInsensitively(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("HeaderCaseInput properties \$first and \$second both use the external name 'x-trace'");
        DtoCompiler::compile(HeaderCaseHandler::class);
    }

    public function testHeaderAndQueryWithTheSameLetterCaseStillCollide(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("both use the external name 'trace'");
        DtoCompiler::compile(HeaderQueryHandler::class);
    }

    public function testNativeListsAndObjectsCannotComeFromAPathOrHeader(): void
    {
        $this->assertCompileFails(HeaderListHandler::class, "HeaderListInput::\$tags reads #[Header], which is one string, so it cannot be list<string>. Use a scalar type or split the string, for example #[Cast(Split::class, ',')].");
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

final class MissingMethodHandler extends TypedHandler
{
}

final readonly class PlainInput
{
    public function __construct(#[Query] public string $name)
    {
    }
}

final class TwoParameterHandler extends TypedHandler
{
    public function handleDto(PlainInput $input, string $extra): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class NullableDtoHandler extends TypedHandler
{
    public function handleDto(?PlainInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class NoReturnHandler extends TypedHandler
{
    public function handleDto(PlainInput $input): string
    {
        return '';
    }
}

final class ScalarHandler extends TypedHandler
{
    public function handleDto(string $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NoSourceInput
{
    public function __construct(public string $name)
    {
    }
}

final class NoSourceHandler extends TypedHandler
{
    public function handleDto(NoSourceInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class TwoSourceInput
{
    public function __construct(
        #[Query]
        #[Body]
        public string $name,
    ) {
    }
}

final class TwoSourceHandler extends TypedHandler
{
    public function handleDto(TwoSourceInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class UnionInput
{
    public function __construct(#[Query] public int|string $value)
    {
    }
}

final class UnionHandler extends TypedHandler
{
    public function handleDto(UnionInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class MixedInput
{
    public function __construct(#[Query] public mixed $value)
    {
    }
}

final class MixedHandler extends TypedHandler
{
    public function handleDto(MixedInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class UntypedArrayInput
{
    public function __construct(#[Query] public array $items)
    {
    }
}

final class UntypedArrayHandler extends TypedHandler
{
    public function handleDto(UntypedArrayInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class AssociativeArrayInput
{
    /** @param array<string, string> $items */
    public function __construct(#[Query] public array $items)
    {
    }
}

final class AssociativeArrayHandler extends TypedHandler
{
    public function handleDto(AssociativeArrayInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NullablePathInput
{
    public function __construct(#[Path] public ?int $id)
    {
    }
}

final class NullablePathHandler extends TypedHandler
{
    public function handleDto(NullablePathInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class DefaultPathInput
{
    public function __construct(#[Path] public int $id = 1)
    {
    }
}

final class DefaultPathHandler extends TypedHandler
{
    public function handleDto(DefaultPathInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadFileInput
{
    public function __construct(#[File] public string $avatar)
    {
    }
}

final class BadFileHandler extends TypedHandler
{
    public function handleDto(BadFileInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NestedQueryChild
{
    public function __construct(#[Query] public string $street)
    {
    }
}

final readonly class NestedQueryInput
{
    public function __construct(#[Body] public NestedQueryChild $address)
    {
    }
}

final class NestedQueryHandler extends TypedHandler
{
    public function handleDto(NestedQueryInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadNameInput
{
    public function __construct(#[Query('a.b')] public string $name)
    {
    }
}

final class BadNameHandler extends TypedHandler
{
    public function handleDto(BadNameInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class DuplicateNameInput
{
    public function __construct(
        #[Query('name')] public string $first,
        #[Query] public string $name,
    ) {
    }
}

final class DuplicateNameHandler extends TypedHandler
{
    public function handleDto(DuplicateNameInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class RangeOnStringInput
{
    public function __construct(#[Query] #[Range(min: 1)] public string $name)
    {
    }
}

final class RangeOnStringHandler extends TypedHandler
{
    public function handleDto(RangeOnStringInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BadDefaultInput
{
    public function __construct(#[Query] #[Range(min: 5)] public int $page = 1)
    {
    }
}

final class BadDefaultHandler extends TypedHandler
{
    public function handleDto(BadDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class UnknownCasterInput
{
    public function __construct(#[Query] #[Cast('NotACaster')] public string $value)
    {
    }
}

final class UnknownCasterHandler extends TypedHandler
{
    public function handleDto(UnknownCasterInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[Consumes('application/json')]
final readonly class JsonFileInput
{
    public function __construct(#[File] public FileUpload $avatar)
    {
    }
}

#[Consumes('application/json')]
final class JsonFileHandler extends TypedHandler
{
    public function handleDto(JsonFileInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[Consumes('text/csv')]
final class CsvConsumesHandler extends TypedHandler
{
    public function handleDto(PlainForConsumes $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[Consumes('application/json', 'application/json')]
final class DuplicateConsumesHandler extends TypedHandler
{
    public function handleDto(PlainForConsumes $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class PlainForConsumes
{
    public function __construct(#[Body] public string $name)
    {
    }
}

final class WritableInput
{
    public function __construct(#[Query] public string $name)
    {
    }
}

final class WritableHandler extends TypedHandler
{
    public function handleDto(WritableInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class EmptyInput
{
}

final class EmptyDtoHandler extends TypedHandler
{
    public function handleDto(EmptyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ListExamplesInput
{
    public function __construct(#[Query(examples: ['a', 'b'])] public string $name)
    {
    }
}

final class ListExamplesHandler extends TypedHandler
{
    public function handleDto(ListExamplesInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class CycleInput
{
    public function __construct(#[Body] public CycleInput $child)
    {
    }
}

final class CycleHandler extends TypedHandler
{
    public function handleDto(CycleInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
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

final readonly class CyclePinInput
{
    public function __construct(#[Body] public CyclePinInput $child)
    {
    }
}

final class CyclePinHandler extends TypedHandler
{
    public function handleDto(CyclePinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class PrivateHandler extends TypedHandler
{
    private function handleDto(CyclePinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class StaticHandler extends TypedHandler
{
    public static function handleDto(CyclePinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class StdClassReturnHandler extends TypedHandler
{
    public function handleDto(CyclePinInput $input): \stdClass
    {
        return new \stdClass();
    }
}

abstract readonly class AbstractPinInput
{
    public function __construct(#[Query] public string $name)
    {
    }
}

final class AbstractDtoHandler extends TypedHandler
{
    public function handleDto(AbstractPinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class EnumDtoHandler extends TypedHandler
{
    public function handleDto(Priority $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

interface PinContract
{
}

final class InterfaceDtoHandler extends TypedHandler
{
    public function handleDto(PinContract $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class IterableInput
{
    public function __construct(#[Query] public iterable $name)
    {
    }
}

final class IterableHandler extends TypedHandler
{
    public function handleDto(IterableInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class FileOnQueryInput
{
    public function __construct(#[Query] public FileUpload $file)
    {
    }
}

final class FileOnQueryHandler extends TypedHandler
{
    public function handleDto(FileOnQueryInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BareFileUploadInput
{
    /** @param list<FileUpload> $files */
    public function __construct(#[Query] public array $files)
    {
    }
}

final class BareFileUploadHandler extends TypedHandler
{
    public function handleDto(BareFileUploadInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class TwinInput
{
    public function __construct(
        #[Body] public AddressInput $left,
        #[Body] public AddressInput $right,
    ) {
    }
}

final class TwinHandler extends TypedHandler
{
    public function handleDto(TwinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['street' => $input->left->street]);
    }
}

final readonly class TwoPathsInput
{
    public function __construct(
        #[Path] public string $id,
        #[Path] public string $slug,
    ) {
    }
}

final class TwoPathsHandler extends TypedHandler
{
    public function handleDto(TwoPathsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class HeaderCaseInput
{
    public function __construct(
        #[Header('X-Trace')] public string $first,
        #[Header('x-trace')] public string $second,
    ) {
    }
}

final class HeaderCaseHandler extends TypedHandler
{
    public function handleDto(HeaderCaseInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class HeaderQueryInput
{
    public function __construct(
        #[Header('trace')] public string $first,
        #[Query('trace')] public string $second,
    ) {
    }
}

final class HeaderQueryHandler extends TypedHandler
{
    public function handleDto(HeaderQueryInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
