<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class AlwaysOkHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['status' => 'ok']);
    }
}
