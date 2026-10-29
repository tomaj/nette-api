<?php

declare(strict_types=1);

namespace App\Api;

use App\Model\UserRepository;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class UserDetailHandler extends BaseHandler
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id'))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $user = $this->users->find((int) $params['id']);
        if ($user === null) {
            return new JsonApiResponse(404, ['status' => 'error', 'message' => 'User not found']);
        }
        return new JsonApiResponse(200, $user);
    }
}
