<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\Repeated;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'twice')]
#[Authorization('a')]
#[Authorization('b')]
final class TwiceHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['handler' => 'TwiceHandler']);
    }
}
