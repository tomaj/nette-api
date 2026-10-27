<?php

declare(strict_types=1);

namespace App\Api;

use Nette\Http\IResponse;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class UserV1Handler extends BaseHandler
{
    public function __construct(private IResponse $httpResponse)
    {
        parent::__construct();
    }

    public function summary(): string
    {
        return 'User detail, one name field';
    }

    // OpenAPI marks the endpoint deprecated: true, the console shows it crossed out
    public function deprecated(): bool
    {
        return true;
    }

    public function params(): array
    {
        return [(new GetInputParam('id'))->setRequired()];
    }

    public function handle(array $params): ResponseInterface
    {
        // deprecated() only changes the docs, clients learn about it from these headers
        $this->httpResponse->setHeader('Deprecation', 'true');
        $this->httpResponse->setHeader('Link', '</api/v2/users?id=' . rawurlencode($params['id']) . '>; rel="successor-version"');

        return new JsonApiResponse(200, ['id' => $params['id'], 'name' => 'Ada Lovelace']);
    }
}
