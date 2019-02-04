<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Response\JsonApiResponse;

class DefaultHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle($params)
    {
        return new JsonApiResponse(404, ['status' => 'error', 'message' => 'Unknown api endpoint']);
    }
}
