<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class NoteCreateHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new JsonInputParam('note', '{"type": "object", "properties": {"text": {"type": "string"}}, "required": ["text"]}'))
                ->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(201, ['note' => ['id' => 2, 'text' => $params['note']['text']]]);
    }
}
