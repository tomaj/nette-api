<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class MultipleOutputTestHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Multiple output test handler';
    }

    public function description(): string
    {
        return 'This API handler is for test multiple ';
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['hello' => 'world']);
    }

    public function outputs(): array
    {
        return [
            new JsonOutput(200, '{"type": "object"}'),
            new JsonOutput(200, '{"type": "string"}'),
        ];
    }
}
