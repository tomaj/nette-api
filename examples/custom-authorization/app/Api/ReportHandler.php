<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * No authorization code here: the endpoint registration decides who may call it.
 */
final class ReportHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Sales report, admins only';
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['orders' => 42, 'revenue' => 1234.5]);
    }
}
