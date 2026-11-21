<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Input\UserDetailInput;
use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'users', '{id}/detail')]
final class UserDetailHandler extends TypedHandler
{
    public function handleDto(UserDetailInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, [
            'id' => $input->id,
            'requestId' => $input->requestId,
        ]);
    }
}
