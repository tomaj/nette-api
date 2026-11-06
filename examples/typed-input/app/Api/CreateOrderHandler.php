<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Input\CreateOrderInput;
use Tomaj\NetteApi\Attribute\Post;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Post(1, 'orders')]
final class CreateOrderHandler extends TypedHandler
{
    public function handleDto(CreateOrderInput $input): ResponseInterface
    {
        return new JsonApiResponse(201, [
            'email' => $input->customerEmail,
            'street' => $input->address->street,
            'items' => count($input->items),
            'quantity' => $input->items[0]->quantity,
        ]);
    }
}
