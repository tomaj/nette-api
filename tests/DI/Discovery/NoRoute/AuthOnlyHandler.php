<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\NoRoute;

use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Authorization('token')]
final class AuthOnlyHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
