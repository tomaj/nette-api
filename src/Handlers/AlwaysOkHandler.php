<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Response\JsonApiResponse;

class AlwaysOkHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle($params)
    {
        return new JsonApiResponse(200, ['status' => 'ok']);
    }
}
