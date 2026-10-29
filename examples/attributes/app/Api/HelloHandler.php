<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

// public: GET /api/v1/hello
#[Get(1, 'hello')]
#[Authorization(false)]
final class HelloHandler extends BaseHandler
{
    // the message comes from the service in config/common.neon
    public function __construct(private readonly string $message)
    {
        parent::__construct();
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['message' => $this->message]);
    }
}
