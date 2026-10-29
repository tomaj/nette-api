<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Discovery\OnMethod;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class MethodHandler extends BaseHandler
{
    #[\Override]
    #[Get(1, 'users')]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
