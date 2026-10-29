<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\Valid\SubOther;

use Tomaj\NetteApi\Attribute\Endpoint;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Endpoint('HEAD', 2, 'extra')]
final class ExtraHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['handler' => 'ExtraHandler']);
    }
}
