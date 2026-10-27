<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserCreateHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            new GetInputParam('dry_run')->setAvailableValues(['0', '1']),
            new PostInputParam('email')->setRequired()->setDescription('E-mail')->addExample('default', 'user@example.com'),
            new PostInputParam('role')->setAvailableValues(['admin' => 'Administrator', 'user' => 'Regular user'])->setDefault('user'),
            new PostInputParam('groups')->setMulti()->setRequired(),
            new FileInputParam('avatar')->setDescription('Avatar image')->setRequired(),
            new FileInputParam('attachments')->setMulti(),
        ];
    }

    public function outputs(): array
    {
        return [
            new JsonOutput(201, '{"$ref": "#/definitions/User", "definitions": {"User": {"type": "object", "properties": {"id": {"type": "integer"}, "email": {"type": ["string", "null"]}}}}}', 'Created'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(201, []);
    }
}
