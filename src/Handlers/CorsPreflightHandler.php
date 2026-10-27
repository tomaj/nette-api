<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CorsPreflightHandler extends BaseHandler implements CorsPreflightHandlerInterface
{
    /**
     * @param array<string,string[]|string> $headers
     */
    public function __construct(
        private readonly Response $response,
        private readonly array $headers = [
            'Access-Control-Allow-Headers' => [
                'Authorization',
                'X-Requested-With',
            ],
        ]
    ) {
        parent::__construct();
    }

    /**
     * @param array<string,mixed> $params
     */
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        foreach ($this->headers as $name => $values) {
            $values = is_array($values) ? $values : [$values];
            foreach ($values as $value) {
                $this->response->addHeader($name, $value);
            }
        }

        return new JsonApiResponse(Response::S200_OK, []);
    }
}
