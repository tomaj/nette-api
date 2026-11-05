<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\TypedBad;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'bad')]
final class BadHandler extends TypedHandler
{
    public function handleDto(BadInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
