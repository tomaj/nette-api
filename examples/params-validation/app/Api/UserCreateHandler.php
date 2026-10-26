<?php

declare(strict_types=1);

namespace App\Api;

use App\UserRepository;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class UserCreateHandler extends BaseHandler
{
    public function __construct(private UserRepository $users)
    {
        parent::__construct();
    }

    public function summary(): string
    {
        return 'Create a user';
    }

    public function tags(): array
    {
        return ['users'];
    }

    public function params(): array
    {
        return [
            // the whole request body, validated against a JSON schema
            (new JsonInputParam('user', (string) file_get_contents(__DIR__ . '/schema/user-create.json')))
                ->setRequired()
                ->addExample('Alice', ['name' => 'Alice', 'email' => 'alice@example.com']),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $user = $this->users->add($params['user']['name'], $params['user']['email']);
        return new JsonApiResponse(201, ['user' => $user]);
    }

    public function outputs(): array
    {
        return [
            new JsonOutput(201, (string) file_get_contents(__DIR__ . '/schema/user.json'), 'The new user'),
        ];
    }
}
