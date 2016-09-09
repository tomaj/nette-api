<?php

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;

class CorsPreflightHandler extends BaseHandler
{
    private $response;

    public function __construct(Response $response)
    {
        parent::__construct();
        $this->response = $response;
    }

    public function handle($params)
    {
        $this->response->addHeader('Access-Control-Allow-Headers', 'Authorization');
        return new JsonApiResponse(Response::S200_OK, []);
    }
}