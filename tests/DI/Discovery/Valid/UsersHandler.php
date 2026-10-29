<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\Valid;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Attribute\Post;
use Tomaj\NetteApi\Attribute\Put;
use Tomaj\NetteApi\Attribute\Patch;
use Tomaj\NetteApi\Attribute\Delete;
use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Attribute\RateLimit;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'users')]
#[Post(1, 'users', 'create')]
#[Put(1, 'users', 'update')]
#[Patch(1, 'users', 'patch')]
#[Delete(1, 'users', 'delete')]
#[Authorization('token')]
#[RateLimit('strict')]
final class UsersHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['handler' => 'UsersHandler']);
    }
}
