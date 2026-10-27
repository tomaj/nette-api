<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * Returns a payload that does not match its declared output, to show output schema validation.
 */
final class BrokenOutputHandler extends BaseHandler
{
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['count' => 'three']);
    }

    public function outputs(): array
    {
        return [
            new JsonOutput(200, '{"type": "object", "properties": {"count": {"type": "integer"}}, "required": ["count"]}'),
        ];
    }
}
