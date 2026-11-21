<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\TraitFixtures;

use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\TraitFixtures\Casters\ItemsCaster;
use Tomaj\NetteApi\Test\Input\TraitFixtures\Lib\NestedFields;
use Tomaj\NetteApi\Test\Input\TraitFixtures\Lib\OrderFields;
use Tomaj\NetteApi\Test\Input\TraitFixtures\Lib\TagFields;

// none of the classes here imports LineItem: a list<LineItem> is resolved in the file of the trait that documents it

final class TraitOrderInput
{
    use OrderFields;
}

final class NestedTraitOrderInput
{
    use NestedFields;
}

final class TraitTagInput
{
    use TagFields;
}

final readonly class TraitCastInput
{
    /**
     * @param list<Items\LineItem> $items
     */
    public function __construct(#[Body] #[Cast(ItemsCaster::class)] public array $items)
    {
    }
}

final class TraitHandlers
{
    /**
     * @return list<class-string<TypedHandler>>
     */
    public static function all(): array
    {
        return [TraitOrderHandler::class, NestedTraitOrderHandler::class, TraitTagHandler::class, TraitCastHandler::class];
    }
}

final class TraitOrderHandler extends TypedHandler
{
    public function handleDto(TraitOrderInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['skus' => array_map(static fn (object $item): mixed => get_object_vars($item)['sku'], $input->items)]);
    }
}

final class NestedTraitOrderHandler extends TypedHandler
{
    public function handleDto(NestedTraitOrderInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['count' => count($input->items)]);
    }
}

final class TraitTagHandler extends TypedHandler
{
    public function handleDto(TraitTagInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['count' => count($input->tags)]);
    }
}

final class TraitCastHandler extends TypedHandler
{
    public function handleDto(TraitCastInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['count' => count($input->items)]);
    }
}
