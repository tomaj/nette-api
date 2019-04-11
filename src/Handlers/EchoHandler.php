<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class EchoHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'status', InputParam::REQUIRED, ['ok', 'error']),
            new InputParam(InputParam::TYPE_GET, 'message'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $params): ResponseInterface
    {
        $status = $params['status'];
        return new JsonApiResponse(200, ['status' => $status, 'params' => $params]);
    }
}
