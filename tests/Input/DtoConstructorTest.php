<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\Field;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\OptionalArgumentHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\UserStatus;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\ConstraintInterface;

final class DtoConstructorTest extends TestCase
{
    public function testConstructorParametersThatTheMapperCannotPass(): void
    {
        $this->assertCompileFails(SecretArgumentHandler::class, 'SecretArgumentInput::__construct() has the required parameter $secret that is not a promoted property');
        $this->assertCompileFails(VariadicArgumentHandler::class, 'VariadicArgumentInput::__construct() parameter $rest is variadic');
        $this->assertCompileFails(ReferenceArgumentHandler::class, 'ReferenceArgumentInput::__construct() parameter $page is passed by reference');
        $this->assertCount(1, DtoCompiler::compile(OptionalArgumentHandler::class)->fields);
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
        // an int in a list<float> default becomes a float, as the same value from a request would
        $this->assertSame([[1.0, 2.5], [true], ['a'], [UserStatus::Active], ['x']], array_map(static fn (Field $field): mixed => $field->default, $fields));
        $this->assertCompileFails(BadFloatListDefaultHandler::class, 'default item 1 is string, but the property is list<float>');
        $this->assertCompileFails(BadBoolListDefaultHandler::class, 'default item 0 is int, but the property is list<bool>');
        $this->assertCompileFails(BadStringListDefaultHandler::class, 'default item 0 is int, but the property is list<string>');
        $this->assertCompileFails(BadEnumListDefaultHandler::class, 'default item 0 is string, but the property is list<' . UserStatus::class . '>');
        $this->assertCompileFails(BadCastListDefaultHandler::class, 'default item 0 is int, but the property is list<string>');
    }

    public function testListDefaultItemsAreChecked(): void
    {
        $this->assertCompileFails(BadListDefaultHandler::class, "BadListDefaultInput::\$items default item 1 is string, but the property is list<int>");
        $this->assertCompileFails(MapDefaultHandler::class, 'MapDefaultInput::$items default must be a list');
    }

    public function testIntItemsOfAFloatListDefaultAreFloats(): void
    {
        $default = DtoCompiler::compile(FloatListDefaultHandler::class)->fields[0]->default;
        $this->assertSame([1.0, 2.5, 3.0], $default);
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

final readonly class FloatListDefaultInput
{
    /** @param list<float> $weights */
    public function __construct(#[Query] public array $weights = [1, 2.5, 3])
    {
    }
}

final class FloatListDefaultHandler extends TypedHandler
{
    public function handleDto(FloatListDefaultInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
