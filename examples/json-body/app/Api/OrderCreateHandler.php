<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class OrderCreateHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Create an order from a JSON body';
    }

    public function params(): array
    {
        return [
            // the whole request body, decoded to an array and checked against the schema
            (new JsonInputParam('order', (string) file_get_contents(__DIR__ . '/schema/order.json')))
                ->setRequired()
                ->addExample('default', ['customer' => 'Ada', 'items' => [['sku' => 'book', 'quantity' => 2]]]),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        // already valid here: customer is a string, every item has sku and quantity >= 1
        $order = $params['order'];
        $pieces = array_sum(array_column($order['items'], 'quantity'));

        return new JsonApiResponse(201, [
            'customer' => $order['customer'],
            'lines' => count($order['items']),
            'pieces' => $pieces,
        ]);
    }
}
