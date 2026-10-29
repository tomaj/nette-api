<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\Valid;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class PlainHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['handler' => 'PlainHandler']);
    }
}
