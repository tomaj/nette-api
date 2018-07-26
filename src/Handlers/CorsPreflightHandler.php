<?php

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;

class CorsPreflightHandler extends BaseHandler
{
    private $response;

    private $headers = [];

    public function __construct(
        Response $response,
        $headers = [
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

    public function handle($params)
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
