<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class GreetingHandler extends BaseHandler
{
    public static int $created = 0;

    public function __construct(public readonly string $greeting = 'Hello')
    {
        parent::__construct();
        self::$created++;
    }

    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['message' => $this->greeting]);
    }
}
