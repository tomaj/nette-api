<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

// public: GET /api/v1/greet?name=Nette, the same kind of endpoint as HelloHandler with a typed input DTO
#[Get(1, 'greet')]
#[Authorization(false)]
final class GreetHandler extends TypedHandler
{
    public function handleDto(GreetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['message' => 'Hello ' . $input->name . '!']);
    }
}
