<?php

declare(strict_types=1);

namespace App\Api;

use App\Model\UserRepository;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class UsersListHandler extends BaseHandler
{
    // UserRepository is autowired, the handler is a normal service
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['users' => $this->users->all()]);
    }
}
