<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\ApiResponse;

class DefaultHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle($params)
    {
        return new ApiResponse(500, ['status' => 'error', 'message' => 'Unknown api endpoint']);
    }
}
