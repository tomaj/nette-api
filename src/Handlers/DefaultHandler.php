<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\IResponse;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class DefaultHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(IResponse::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'Unknown api endpoint']);
    }
}
