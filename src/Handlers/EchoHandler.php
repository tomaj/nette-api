<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Params\GetInputParam;
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
            (new GetInputParam('status'))->setRequired()->setAvailableValues(['ok', 'error']),
            new GetInputParam('message'),
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
