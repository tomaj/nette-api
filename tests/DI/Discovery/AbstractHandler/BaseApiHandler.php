<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\AbstractHandler;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'base')]
abstract class BaseApiHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['handler' => 'BaseApiHandler']);
    }
}
