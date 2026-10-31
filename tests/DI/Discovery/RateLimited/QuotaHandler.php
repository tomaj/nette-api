<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\RateLimited;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Attribute\Post;
use Tomaj\NetteApi\Attribute\RateLimit;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'quota')]
#[Post(1, 'quota', 'create')]
#[RateLimit(limit: 1000, interval: '1 hour', algorithm: 'tokenBucket', key: ['identity', 'endpoint'])]
final class QuotaHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
