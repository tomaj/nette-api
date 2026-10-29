<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\Valid;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Attribute\RateLimit;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get('1.1', 'hello')]
#[Authorization(false)]
#[RateLimit(false)]
final class HelloHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['handler' => 'HelloHandler']);
    }
}
