<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use League\Fractal\TransformerAbstract;
use Nette\Http\IResponse;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class TransformerTestHandler extends BaseHandler
{
    private TransformerAbstract $transformer;

    public function __construct(TransformerAbstract $transformer)
    {
        $this->transformer = $transformer;
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['id' => 5]);
    }

    public function outputs(): array
    {
        return [
            new JsonOutput(IResponse::S200_OK, json_encode(method_exists($this->transformer, 'schema') ? $this->transformer->schema() : []), 'Successful request'),
        ];
    }
}
