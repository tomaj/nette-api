<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class HelloHandler extends BaseHandler
{
    // the message comes from the neon statement App\Api\HelloHandler('...')
    public function __construct(private readonly string $message)
    {
        parent::__construct();
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['message' => $this->message]);
    }
}
