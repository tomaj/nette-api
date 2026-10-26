<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * Runs only when the authorization of its endpoint passed.
 */
final class WhoAmIHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Tell how the request was authorized';
    }

    public function handle(array $params): ResponseInterface
    {
        $endpoint = $this->getEndpoint();
        return new JsonApiResponse(200, [
            'status' => 'ok',
            'endpoint' => $endpoint?->getUrl(),
        ]);
    }
}
