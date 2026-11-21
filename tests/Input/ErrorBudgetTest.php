<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\DtoMapper;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\ManyIdsHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\BodyFieldHandler;
use Tomaj\NetteApi\Validation\Length;

final class ErrorBudgetTest extends TestCase
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

    public function testTheBudgetStopsInsideAnObjectToo(): void
    {
        $this->json(['ids' => array_fill(0, 99, 'x'), 'inner' => new \stdClass()]);
        $errors = $this->errors(new BudgetObjectHandler());

        $this->assertSame(['Field is required'], $errors['inner.a']);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body']);
        $this->assertArrayNotHasKey('inner', $errors);
        $this->assertArrayNotHasKey('inner.b', $errors);
        $this->assertArrayNotHasKey('inner.c', $errors);
    }

    public function testTheBudgetEndsAtTheTopLevelToo(): void
    {
        $this->json(['ids' => array_fill(0, 99, 'x'), 'x' => 'a', 'y' => 'b']);
        $errors = $this->errors(new BudgetTopHandler());

        $this->assertSame(['Value must be an integer'], $errors['x']);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body']);
        $this->assertArrayNotHasKey('y', $errors);
    }

    public function testConstraintMessagesCountTowardsTheBudget(): void
    {
        $this->json(['items' => array_fill(0, 150, ['name' => 'too long'])]);
        $errors = $this->errors(new ConstraintBudgetHandler());

        $this->assertCount(101, $errors);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body']);
        $this->assertArrayHasKey('items[99].name', $errors);
        $this->assertArrayNotHasKey('items[100].name', $errors);
    }

    public function testTheNoteAboutAnExhaustedBudgetBelongsToTheFirstFieldWhenNothingReadsABody(): void
    {
        $_GET = ['ids' => array_fill(0, 150, 'x')];
        $metadata = DtoCompiler::compile(QueryBudgetHandler::class);
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
        $metadata = DtoCompiler::compile(MixedBudgetHandler::class);
        $mapper = new DtoMapper($metadata);

        $this->assertSame([], $mapper->errors($metadata->fields[0]));
        $this->assertArrayHasKey('body', $mapper->errors($metadata->fields[1]));
    }

    public function testAnErrorOfTheBodyAndTheNoteShareTheKey(): void
    {
        $this->assertNull($this->post('application/json', '{"a":"x"}', new BodyFieldHandler()));
        RequestInput::reset();
        $errors = $this->post('application/json', '{"ids":' . json_encode(array_fill(0, 101, 'x')) . '}', new BodyBudgetHandler());

        $this->assertCount(101, $errors ?? []);
        $this->assertSame(['Only the first 100 errors are reported, the rest of the request was not checked'], $errors['body'] ?? []);
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

final readonly class BudgetInner
{
    public function __construct(#[Body] public string $a, #[Body] public string $b, #[Body] public string $c)
    {
    }
}

final readonly class BudgetObjectInput
{
    /** @param list<int> $ids */
    public function __construct(#[Body] public array $ids, #[Body] public BudgetInner $inner)
    {
    }
}

final class BudgetObjectHandler extends TypedHandler
{
    public function handleDto(BudgetObjectInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NamedItem
{
    public function __construct(#[Body] #[Length(max: 3)] public string $name)
    {
    }
}

final readonly class ConstraintBudgetInput
{
    /** @param list<NamedItem> $items */
    public function __construct(#[Body] public array $items)
    {
    }
}

final class ConstraintBudgetHandler extends TypedHandler
{
    public function handleDto(ConstraintBudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BudgetTopInput
{
    /** @param list<int> $ids */
    public function __construct(#[Body] public array $ids, #[Body] public int $x, #[Body] public int $y)
    {
    }
}

final class BudgetTopHandler extends TypedHandler
{
    public function handleDto(BudgetTopInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class QueryBudgetInput
{
    /** @param list<int> $ids */
    public function __construct(#[Query] public array $ids = [], #[Query] public int $page = 1)
    {
    }
}

final class QueryBudgetHandler extends TypedHandler
{
    public function handleDto(QueryBudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BodyBudgetInput
{
    /** @param list<int> $ids */
    public function __construct(#[Body] public array $ids = [])
    {
    }
}

final class BodyBudgetHandler extends TypedHandler
{
    public function handleDto(BodyBudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class MixedBudgetInput
{
    /** @param list<int> $ids */
    public function __construct(#[Query] public int $page = 1, #[Body] public array $ids = [])
    {
    }
}

final class MixedBudgetHandler extends TypedHandler
{
    public function handleDto(MixedBudgetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
