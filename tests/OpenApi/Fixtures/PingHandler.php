<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class PingHandler extends BaseHandler
{
    public function outputs(): array
    {
        return [
            new JsonOutput(200, '{"type": "object", "properties": {"status": {"type": "string"}}}'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['status' => 'ok']);
    }
}
