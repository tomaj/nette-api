<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\RawInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class RawBodyHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Import raw data';
    }

    public function params(): array
    {
        return [
            (new RawInputParam('csv'))->setDescription('CSV content')->setRequired()->setExample("id;name\n1;John"),
            (new JsonInputParam('meta', '{"type": "object"}'))->setExample('{"source": "upload"}'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
