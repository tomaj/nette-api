<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class AvatarUploadHandler extends BaseHandler
{
    private const MAX_BYTES = 100_000;

    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    public function __construct(private string $storageDir)
    {
        parent::__construct();
    }

    public function summary(): string
    {
        return 'Upload an avatar';
    }

    public function params(): array
    {
        return [
            // $_FILES['avatar']: name, type, tmp_name, error, size
            (new FileInputParam('avatar'))->setRequired()->setDescription('PNG or JPEG, at most 100 kB'),
            (new PostInputParam('user'))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $file = $params['avatar'];

        // nette-api checks only that the file field is there; everything else is up to the handler
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return new JsonApiResponse(400, ['status' => 'error', 'message' => 'Upload failed']);
        }
        if ($file['size'] > self::MAX_BYTES) {
            return new JsonApiResponse(413, ['status' => 'error', 'message' => 'The file is larger than 100 kB']);
        }

        // never trust $file['type'], it comes from the client
        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$type])) {
            return new JsonApiResponse(415, ['status' => 'error', 'message' => "Type $type is not allowed"]);
        }

        // never use $file['name'] as a path either
        $target = $this->storageDir . '/' . bin2hex(random_bytes(8)) . '.' . self::TYPES[$type];
        move_uploaded_file($file['tmp_name'], $target);

        return new JsonApiResponse(201, ['user' => $params['user'], 'type' => $type, 'size' => $file['size']]);
    }
}
