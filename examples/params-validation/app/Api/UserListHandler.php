<?php

declare(strict_types=1);

namespace App\Api;

use App\UserRepository;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\InputType;

final class UserListHandler extends BaseHandler
{
    public function __construct(private UserRepository $users)
    {
        parent::__construct();
    }

    public function summary(): string
    {
        return 'List users';
    }

    public function tags(): array
    {
        return ['users'];
    }

    public function params(): array
    {
        return [
            // ?status=active, only the listed values pass
            (new GetInputParam('status'))
                ->setAvailableValues(['active', 'blocked'])
                ->setDescription('Only users with this status'),
            // ?id[]=1&id[]=3, every item must be an integer
            (new GetInputParam('id', InputType::INTEGER))
                ->setMulti()
                ->setDescription('Only users with these ids'),
            // ?limit=2, converted to int before handle() gets it
            (new GetInputParam('limit', InputType::INTEGER))
                ->setDefault(10)
                ->setDescription('Maximum number of users'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        // a value that is not numeric (?limit=abc) arrives as null, not as an error
        $limit = $params['limit'] ?? 10;
        $users = $this->users->find($params['status'], $params['id'] ?? [], $limit);
        return new JsonApiResponse(200, ['users' => $users]);
    }

    public function outputs(): array
    {
        return [
            new JsonOutput(200, (string) file_get_contents(__DIR__ . '/schema/user-list.json'), 'The matching users'),
        ];
    }
}
