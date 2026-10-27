<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\PutInputParam;
use Tomaj\NetteApi\Params\RawInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserUpdateHandler extends BaseHandler
{
    public function params(): array
    {
        $schema = '{"type": "object", "properties": {"address": {"$ref": "#/definitions/Address"}, "age": {"type": ["integer", "string"]}}, "required": ["address"], "definitions": {"Address": {"type": "object", "properties": {"city": {"type": ["string", "null"]}}}}}';
        return [
            new JsonInputParam('data', $schema)->setRequired()->setDescription('User data')
                ->addExample('minimal', ['address' => ['city' => null]])
                ->addExample('full', '{"address": {"city": "Bratislava"}, "age": 30}'),
            new RawInputParam('note')->setDescription('Raw note')->addExample('a', 'first')->addExample('b', 'second'),
            new PutInputParam('flag')->addExample('default', 'yes'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
