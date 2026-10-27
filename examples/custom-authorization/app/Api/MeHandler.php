<?php

declare(strict_types=1);

namespace App\Api;

use App\CurrentUser;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class MeHandler extends BaseHandler
{
    public function __construct(private CurrentUser $currentUser)
    {
        parent::__construct();
    }

    public function summary(): string
    {
        return 'Who is calling';
    }

    public function handle(array $params): ResponseInterface
    {
        // set by RoleTokenAuthorization; null when the endpoint uses an API key only
        $user = $this->currentUser->get();
        return new JsonApiResponse(200, [
            'name' => $user?->name ?? 'api-key client',
            'roles' => $user?->roles ?? [],
        ]);
    }
}
