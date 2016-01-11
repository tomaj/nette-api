<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;

class EchoHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
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
        return new JsonApiResponse(200, ['status' => $status, 'params' => $params]);
    }
}
