<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures;

use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\Consumes;
use Tomaj\NetteApi\Input\DateFormat;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\Range;

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

final readonly class FormInput
{
    public function __construct(
        #[Body] public ?string $a = null,
    ) {
    }
}

final class FormHandler extends TypedHandler
{
    public function handleDto(FormInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BodyFieldInput
{
    public function __construct(#[Body] public ?string $a = null)
    {
    }
}

final class BodyFieldHandler extends TypedHandler
{
    public function handleDto(BodyFieldInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
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

final readonly class TwoExamplesInput
{
    public function __construct(#[Query(examples: ['one' => 1, 'two' => 2])] public int $page = 1)
    {
    }
}

final class TwoExamplesHandler extends TypedHandler
{
    public function handleDto(TwoExamplesInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class DescribedInput
{
    public function __construct(
        #[Body(description: 'Customer email')] public ?string $email = null,
    ) {
    }
}

#[Consumes('application/json')]
final class DescribedHandler extends TypedHandler
{
    public function handleDto(DescribedInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
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
