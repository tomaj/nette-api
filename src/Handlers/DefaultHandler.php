<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class DefaultHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(500, ['status' => 'error', 'message' => 'Unknown api endpoint']);
    }
}
