<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

// GET /api/v1/greet?name=Nette, registered in config/common.neon like HelloHandler, with a typed input DTO
final class GreetHandler extends TypedHandler
{
    public function handleDto(GreetInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['message' => 'Hello ' . $input->name . '!']);
    }
}
