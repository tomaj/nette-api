<?php

declare(strict_types=1);

namespace App\Api;

use Nette\Utils\Json;
use RuntimeException;
use Tomaj\NetteApi\Error\ProblemDetails;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\OpenApi\ResponseBuilder;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class OrderCreateHandler extends BaseHandler
{
    private string $problemTypeBaseUri;

    public function __construct(string $problemTypeBaseUri)
    {
        parent::__construct();
        // the same base as ProblemDetailsErrorHandler, which adds a missing trailing slash too
        $this->problemTypeBaseUri = rtrim($problemTypeBaseUri, '/') . '/';
    }

    public function summary(): string
    {
        return 'Create an order';
    }

    public function params(): array
    {
        return [
            (new PostInputParam('product'))->setRequired(),
            (new PostInputParam('quantity'))->setRequired()->setAvailableValues(['1', '2', '3']),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        if ($params['product'] === 'crash') {
            // ProblemDetailsErrorHandler::handle() answers 500, the message only with error details on
            throw new RuntimeException('SQLSTATE[08006] connection to orders-db:5432 refused');
        }

        if ($params['product'] === 'sold-out') {
            // a business error: the handler builds the problem itself, with an own type and extension members
            return new ProblemDetailsResponse(new ProblemDetails(
                status: 409,
                title: 'Out of stock',
                detail: 'Product sold-out has 0 pieces left, 2 requested.',
                type: $this->problemTypeBaseUri . 'out-of-stock',
                instance: '/api/v1/orders',
                extensions: ['product' => 'sold-out', 'available' => 0],
            ));
        }

        return new JsonApiResponse(201, ['product' => $params['product'], 'quantity' => (int) $params['quantity']]);
    }

    public function outputs(): array
    {
        return [
            new JsonOutput(201, '{"type":"object","required":["product","quantity"]}', 'The new order'),
            // outputs are validated in development: a problem the handler returns must be declared too,
            // otherwise ApiPresenter replaces it with a 500 from handleSchema()
            new JsonOutput(409, Json::encode(ResponseBuilder::problemDetailsSchema()), 'Out of stock'),
        ];
    }
}
