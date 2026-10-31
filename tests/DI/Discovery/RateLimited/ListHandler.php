<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\RateLimited;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Attribute\RateLimit;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'list')]
#[RateLimit(['perIp', 'named'])]
final class ListHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
