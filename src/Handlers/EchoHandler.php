<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\ApiResponse;
use Tomaj\NetteApi\Params\InputParam;

class EchoHandler extends BaseHandler
{
    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'status', InputParam::REQUIRED, ['ok', 'error']),
            new InputParam(InputParam::TYPE_GET, 'message'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle($params)
    {
        $status = $params['status'];
        return new ApiResponse(200, ['status' => $status, 'params' => $params]);
    }
}
