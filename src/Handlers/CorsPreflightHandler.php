<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CorsPreflightHandler extends BaseHandler implements CorsPreflightHandlerInterface
{
    private $response;

    private $headers = [];

    public function __construct(
        Response $response,
        array $headers = [
            'Access-Control-Allow-Headers' => [
                'Authorization',
                'X-Requested-With',
            ],
        ]
    ) {
        parent::__construct();
        $this->response = $response;
        $this->headers = $headers;
    }

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
