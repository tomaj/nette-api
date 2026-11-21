<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Input\AvatarInput;
use Tomaj\NetteApi\Attribute\Post;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Post(1, 'avatar')]
final class AvatarUploadHandler extends TypedHandler
{
    public function handleDto(AvatarInput $input): ResponseInterface
    {
        $avatar = $input->avatar;
        if ($avatar->error !== UPLOAD_ERR_OK) {
            return new JsonApiResponse(400, ['status' => 'error', 'message' => 'upload failed']);
        }

        return new JsonApiResponse(201, [
            'name' => $input->displayName,
            'file' => $avatar->originalName,
            'size' => $avatar->size,
        ]);
    }
}
