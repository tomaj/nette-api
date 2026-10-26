<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class HelloHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Say hello';
    }

    public function params(): array
    {
        return [
            (new GetInputParam('name'))->setDefault('world')->setDescription('Who to greet'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['message' => 'Hello ' . $params['name'] . '!']);
    }
}
