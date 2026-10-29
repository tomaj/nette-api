<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\Valid\Sub;

use Tomaj\NetteApi\Attribute\Endpoint;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Endpoint('options', 2, 'misc')]
final class OptionsHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['handler' => 'OptionsHandler']);
    }
}
