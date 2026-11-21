<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\Field;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Test\Input\TraitFixtures\Items\LineItem;
use Tomaj\NetteApi\Test\Input\TraitFixtures\TraitHandlers;
use Tomaj\NetteApi\Test\Input\TraitFixtures\TraitOrderHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class DtoPhpDocTest extends TestCase
{
    public function testAnUntypedParamTagFallsBackToTheVarOfTheProperty(): void
    {
        $this->assertSame('int', DtoCompiler::compile(VarFallbackHandler::class)->fields[0]->listItem);
    }

    public function testEveryCommonPhpDocSpellingOfAListIsRead(): void
    {
        $fields = DtoCompiler::compile(DocFormsHandler::class)->fields;

        $this->assertSame(
            ['string', 'string', 'string', 'string', 'string', 'string', 'int', 'float'],
            array_map(static fn (Field $field): string => (string) $field->listItem, $fields),
        );
    }

    public function testAListDocumentedInATraitResolvesInTheFileOfTheTrait(): void
    {
        foreach (TraitHandlers::all() as $handler) {
            $this->assertSame(LineItem::class, DtoCompiler::compile($handler)->fields[0]->listItem, $handler);
        }

        RequestInput::reset();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"items":[{"sku":"A"},{"sku":"B"}]}');
        $handler = new TraitOrderHandler();
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError());
        $this->assertSame(['skus' => ['A', 'B']], $handler->handle($processor->getValues())->getPayload());
    }

    /**
     * @return \Iterator<string, array{class-string<TypedHandler>, string}>
     */
    public static function descriptions(): \Iterator
    {
        yield 'var with a description' => [VarDescriptionHandler::class, 'string'];
        yield 'var without a space before the end of the comment' => [VarTightHandler::class, 'int'];
        yield 'non-empty-list with a description' => [VarNonEmptyHandler::class, 'string'];
        yield 'phpstan var with a nested generic and a description' => [VarGenericHandler::class, 'int'];
    }

    /**
     * @param class-string<TypedHandler> $handler
     */
    #[DataProvider('descriptions')]
    public function testAVarTypeMayBeFollowedByADescription(string $handler, string $item): void
    {
        $this->assertSame($item, DtoCompiler::compile($handler)->fields[0]->listItem);
    }
}

final readonly class DocFormsInput
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

final class DocFormsHandler extends TypedHandler
{
    public function handleDto(DocFormsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class VarFallbackInput
{
    /** @param $a */
    public function __construct(
        /** @var list<int> */
        #[Body] public array $a = [],
    ) {
    }
}

final class VarFallbackHandler extends TypedHandler
{
    public function handleDto(VarFallbackInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class VarDescriptionInput
{
    public function __construct(
        /** @var list<string> Names of the things */
        #[Body] public array $names = [],
    ) {
    }
}

final class VarDescriptionHandler extends TypedHandler
{
    public function handleDto(VarDescriptionInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class VarTightInput
{
    public function __construct(
        /** @var list<int>*/
        #[Body] public array $ids = [],
    ) {
    }
}

final class VarTightHandler extends TypedHandler
{
    public function handleDto(VarTightInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class VarNonEmptyInput
{
    public function __construct(
        /** @var non-empty-list<string> Names of the things */
        #[Body] public array $names = ['a'],
    ) {
    }
}

final class VarNonEmptyHandler extends TypedHandler
{
    public function handleDto(VarNonEmptyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class VarGenericInput
{
    public function __construct(
        /** @phpstan-var list<int> Numbers: any of them */
        #[Body] public array $numbers = [],
    ) {
    }
}

final class VarGenericHandler extends TypedHandler
{
    public function handleDto(VarGenericInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
