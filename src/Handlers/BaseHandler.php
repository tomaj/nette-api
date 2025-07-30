<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\TransformerAbstract;
use Tomaj\NetteApi\EndpointInterface;

abstract class BaseHandler implements ApiHandlerInterface
{
    public function __construct(
        private readonly Manager $fractal = new Manager(),
        private readonly ?EndpointInterface $endpoint = null
    ) {
    }

    protected function createItemResource(mixed $data, TransformerAbstract $transformer, ?string $resourceKey = null): Item
    {
        return new Item($data, $transformer, $resourceKey);
    }

    protected function createCollectionResource(array $data, TransformerAbstract $transformer, ?string $resourceKey = null): Collection
    {
        return new Collection($data, $transformer, $resourceKey);
    }

    protected function getFractal(): Manager
    {
        return $this->fractal;
    }

    protected function getEndpoint(): ?EndpointInterface
    {
        return $this->endpoint;
    }

    #[\Deprecated(
        message: "Use getEndpoint() instead",
        since: "8.4"
    )]
    protected function getEndpointIdentifier(): ?EndpointInterface
    {
        return $this->getEndpoint();
    }

    /**
     * Transform data using Fractal
     */
    protected function transform(Item|Collection $resource): array
    {
        return $this->fractal->createData($resource)->toArray();
    }
}
