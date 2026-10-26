<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class NoteListHandler extends BaseHandler
{
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['notes' => [['id' => 1, 'text' => 'Buy milk']]]);
    }
}
