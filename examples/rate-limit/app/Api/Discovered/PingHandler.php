<?php

declare(strict_types=1);

namespace App\Api\Discovered;

use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Attribute\RateLimit;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * An inline limit on the class instead of the default perIp: 2 requests per minute per IP, with a token bucket
 */
#[Get(1, 'ping')]
#[Authorization(false)]
#[RateLimit(limit: 2, interval: '1 minute', algorithm: 'tokenBucket', name: 'ping')]
final class PingHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Pong, at most 2 per minute per IP';
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['pong' => true]);
    }
}
