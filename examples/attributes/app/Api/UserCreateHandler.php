<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Attribute\Post;
use Tomaj\NetteApi\Attribute\RateLimit;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

// POST /api/v1/users, at most 2 calls per minute
#[Post(1, 'users')]
#[RateLimit('strict')]
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
