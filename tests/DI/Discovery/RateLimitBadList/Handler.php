<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\RateLimitBadList;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Attribute\RateLimit;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'users')]
#[RateLimit([5])]
final class Handler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
