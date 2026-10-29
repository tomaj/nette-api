<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class UserCreateHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new PostInputParam('name'))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(201, ['id' => 3, 'name' => $params['name']]);
    }
}
