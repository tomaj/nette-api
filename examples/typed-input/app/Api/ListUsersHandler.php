<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Input\ListUsersInput;
use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'users', 'list')]
final class ListUsersHandler extends TypedHandler
{
    public function handleDto(ListUsersInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, [
            'page' => $input->page,
            'status' => $input->status?->value,
        ]);
    }
}
