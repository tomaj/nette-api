<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;
use Tomaj\NetteApi\OpenApi\ResponseBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\DtoMapper;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class Round10Test extends TestCase
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
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['REQUEST_METHOD']);
    }

    public function testErrorsOfALongListAreCapped(): void
    {
        $this->json(['ids' => array_fill(0, 5000, 'x')]);
        $errors = $this->errors(new ManyIdsHandler());

        $this->assertCount(DtoMapper::MAX_ERRORS + 1, $errors);
        $this->assertSame(['Value must be an integer'], $errors['ids[0]']);
        $this->assertSame(['Value must be an integer'], $errors['ids[99]']);
        $this->assertArrayNotHasKey('ids[100]', $errors);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body']);
        $this->assertArrayNotHasKey('ids', $errors);
    }

    public function testTheBudgetIsOnePerRequestAcrossNestedLists(): void
    {
        $leaf = array_fill(0, 100, new \stdClass());
        $middle = array_fill(0, 100, ['cs' => $leaf]);
        $outer = array_fill(0, 100, ['bs' => $middle]);
        $this->json(['as' => $outer]);

        $errors = $this->errors(new NestedListsHandler());
        $messages = array_merge(...array_values($errors));

        $this->assertLessThanOrEqual(DtoMapper::MAX_ERRORS + 1, count($messages));
        $this->assertContains('Only the first 100 errors are reported, the rest of the request was not checked', $messages);
        $this->assertCount(1, array_filter($messages, static fn (string $message): bool => str_starts_with($message, 'Only the first')));
    }

    public function testTheBudgetAlsoStopsLaterFields(): void
    {
        $this->json(['ids' => array_fill(0, 150, 'x'), 'other' => 'y']);
        $errors = $this->errors(new BudgetHandler());

        $this->assertArrayNotHasKey('other', $errors);
        $this->assertCount(101, $errors);
    }

    public function testNothingIsLeftUncheckedUpToTheCapSoThereIsNoNote(): void
    {
        $this->json(['ids' => array_fill(0, 99, 'x')]);
        $errors = $this->errors(new ManyIdsHandler());
        $this->assertCount(99, $errors);
        $this->assertArrayNotHasKey('ids', $errors);

        // exactly the budget: nothing was left unchecked, so there is nothing to apologise for
        $this->json(['ids' => array_fill(0, 100, 'x')]);
        $errors = $this->errors(new ManyIdsHandler());
        $this->assertCount(100, $errors);
        $this->assertArrayNotHasKey('body', $errors);

        $this->json(['ids' => array_fill(0, 101, 'x')]);
        $errors = $this->errors(new ManyIdsHandler());
        $this->assertCount(101, $errors);
        $this->assertArrayHasKey('body', $errors);
        $this->assertArrayNotHasKey('ids[100]', $errors);
    }

    public function testManyInvalidItemsAreCheckedInLinearTime(): void
    {
        $this->json(['ids' => array_merge(array_fill(0, 100, 1), array_fill(0, 60000, 'x'))]);
        $start = microtime(true);
        $this->errors(new ManyIdsHandler());
        $this->assertLessThan(2.0, microtime(true) - $start);
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

    public function testOpenApiListsTheUnsupportedMediaTypeResponseOfATypedBody(): void
    {
        foreach ([false, true] as $problemDetails) {
            $builder = new ResponseBuilder($problemDetails);
            $withBody = $builder->build(new ManyIdsHandler(), new NoAuthorization(), true, new SchemaRegistry());
            $this->assertSame([400, 415, 500], array_keys($withBody));
            $this->assertSame('Unsupported media type', $withBody[415]['description']);
            $this->assertArrayHasKey($problemDetails ? 'application/problem+json; charset=utf-8' : 'application/json; charset=utf-8', $withBody[415]['content']);

            $queryOnly = $builder->build(new SplitHandler(), new NoAuthorization(), true, new SchemaRegistry());
            $this->assertSame([400, 500], array_keys($queryOnly));
        }

        $classic = new ResponseBuilder()->build(new EchoHandler(), new NoAuthorization(), true, new SchemaRegistry());
        $this->assertArrayNotHasKey(415, $classic);
    }

    public function testANullableEnumListsNullInTheBodyAndNothingNullableOnTheWire(): void
    {
        $params = DtoCompiler::compile(LevelHandler::class)->inputParams();

        $this->assertSame(['type' => 'string', 'enum' => ['low', 'high', null], 'nullable' => true], $params[1]->getOpenApiSchema());
        $this->assertSame(['type' => 'string', 'enum' => ['low', 'high']], $params[2]->getOpenApiSchema());
        $this->assertSame([
            'type' => 'object',
            'properties' => ['text' => ['type' => 'string'], 'author' => ['type' => 'string', 'nullable' => true]],
            'required' => ['text'],
            'nullable' => true,
        ], $params[0]->getOpenApiSchema());
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
    private function errors(TypedHandler $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertTrue($processor->isError());

        return $processor->getErrors();
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
}

final readonly class Round10Note
{
    public function __construct(#[Body] public string $text, #[Body] public ?string $author = null)
    {
    }
}

enum Round10Level: string
{
    case Low = 'low';
    case High = 'high';
}

final readonly class LevelInput
{
    public function __construct(
        #[Body] public ?Round10Note $note = null,
        #[Body] public ?Round10Level $level = null,
        #[Query] public ?Round10Level $filter = null,
    ) {
    }
}

final class LevelHandler extends TypedHandler
{
    public function handleDto(LevelInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ManyIdsInput
{
    /** @param list<int> $ids */
    public function __construct(#[Body] public array $ids)
    {
    }
}

final class ManyIdsHandler extends TypedHandler
{
    public function handleDto(ManyIdsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['count' => count($input->ids)]);
    }
}

final readonly class SplitInput
{
    /** @param list<string> $tags */
    public function __construct(
        #[Query] #[Cast(Split::class, ',')] public array $tags = [],
        #[Query] #[Cast(UpperCaster::class)] public string $label = 'plain',
    ) {
    }
}

final class SplitHandler extends TypedHandler
{
    public function handleDto(SplitInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['tags' => $input->tags]);
    }
}

final readonly class UpperCaster implements CasterInterface
{
    public function cast(mixed $value): string
    {
        return strtoupper(is_string($value) ? $value : '');
    }
}

final readonly class BudgetLeaf
{
    public function __construct(#[Body] public string $x)
    {
    }
}

final readonly class BudgetMiddle
{
    /** @param list<BudgetLeaf> $cs */
    public function __construct(#[Body] public array $cs)
    {
    }
}

final readonly class BudgetOuter
{
    /** @param list<BudgetMiddle> $bs */
    public function __construct(#[Body] public array $bs)
    {
    }
}

final readonly class NestedListsInput
{
    /** @param list<BudgetOuter> $as */
    public function __construct(#[Body] public array $as)
    {
    }
}

final class NestedListsHandler extends TypedHandler
{
    public function handleDto(NestedListsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BudgetInput
{
    /** @param list<int> $ids */
    public function __construct(#[Body] public array $ids, #[Body] public int $other)
    {
    }
}

final class BudgetHandler extends TypedHandler
{
    public function handleDto(BudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
