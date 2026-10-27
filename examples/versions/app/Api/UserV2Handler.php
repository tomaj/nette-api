<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class UserV2Handler extends BaseHandler
{
    public function summary(): string
    {
        return 'User detail, name split in two fields';
    }

    public function params(): array
    {
        return [(new GetInputParam('id'))->setRequired()];
    }

    public function handle(array $params): ResponseInterface
    {
        // the breaking change: name is split, so it is a new version, v1 stays as it was
        return new JsonApiResponse(200, ['id' => $params['id'], 'first_name' => 'Ada', 'last_name' => 'Lovelace']);
    }
}
