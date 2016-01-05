<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\ApiResponse;

class AlwaysOkHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle($params)
    {
        return new ApiResponse(200, ['status' => 'ok']);
    }
}
