<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class StatusHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'A health check, public and without a rate limit';
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['status' => 'ok']);
    }
}
