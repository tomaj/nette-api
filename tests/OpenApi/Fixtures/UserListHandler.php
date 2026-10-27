<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Output\RedirectOutput;
use Tomaj\NetteApi\Params\CookieInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UserListHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'List users';
    }

    public function description(): string
    {
        return 'Returns paginated list of users';
    }

    public function tags(): array
    {
        return ['users', 'listing'];
    }

    public function deprecated(): bool
    {
        return true;
    }

    public function params(): array
    {
        return [
            (new GetInputParam('status'))->setAvailableValues(['active' => 'Active users', 'blocked' => 'Blocked users'])->setDescription('User status'),
            (new GetInputParam('order'))->setAvailableValues(['asc', 'desc'])->setDefault('asc'),
            (new GetInputParam('ids'))->setMulti()->setRequired()->setExample('1'),
            (new GetInputParam('page'))->setDescription('Page number'),
            (new CookieInputParam('session'))->setDescription('Session id'),
        ];
    }

    public function outputs(): array
    {
        $schema = '{"type": "object", "properties": {"users": {"type": "array", "items": {"$ref": "#/definitions/User"}}, "next": {"type": ["string", "null"]}}, "definitions": {"User": {"type": "object", "properties": {"id": {"type": "integer"}, "email": {"type": ["string", "null"]}}}}}';
        return [
            (new JsonOutput(200, $schema, 'List of users'))->setExample(['users' => [['id' => 1, 'email' => null]], 'next' => null]),
            (new JsonOutput(200, '{"type": "string"}', 'Empty list message')),
            (new JsonOutput(206, '{"type": "object"}', 'Partial'))->addExample('first', '{"a": 1}')->addExample('second', ['b' => 2]),
            new RedirectOutput(302, 'Redirect to new listing'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
