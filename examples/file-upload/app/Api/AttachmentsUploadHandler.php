<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class AttachmentsUploadHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Upload several files at once';
    }

    public function params(): array
    {
        return [
            // files[]=@a.txt files[]=@b.txt; setMulti() turns $_FILES into one array per file
            (new FileInputParam('files'))->setMulti()->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $files = [];
        foreach ($params['files'] as $file) {
            $files[] = ['name' => basename($file['name']), 'size' => $file['size'], 'ok' => $file['error'] === UPLOAD_ERR_OK];
        }

        return new JsonApiResponse(200, ['files' => $files]);
    }
}
