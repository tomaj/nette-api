<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class QuoteHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'A quote, at most three per hour per API key';
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['quote' => 'Simplicity is prerequisite for reliability.']);
    }
}
