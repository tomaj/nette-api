<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\BadVersion;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get('v1', 'users')]
final class VersionHandler extends BaseHandler
{
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
