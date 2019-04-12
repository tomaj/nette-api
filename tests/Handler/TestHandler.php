<?php

namespace Tomaj\NetteApi\Test\Handler;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class TestHandler extends BaseHandler
{
    public function __construct()
    {
    }

    public function params(): array
    {
        return [
            new GetInputParam('use_fractal'),
        ];
    }

    public function summary(): string
    {
        return 'Test handler';
    }

    public function description(): string
    {
        return 'This API handler is for test purpose and it is marked as deprecated';
    }

    public function tags(): array
    {
        return ['test'];
    }

    public function deprecated(): bool
    {
        return true;
    }

    public function handle(array $params): ResponseInterface
    {
        if (isset($params['use_fractal']) && $params['use_fractal'] === true) {
            $this->getFractal()->createData([])->toArray();
        }
        return new JsonApiResponse(200, ['hello' => 'world']);
    }
}
