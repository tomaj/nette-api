<?php

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;

class CorsPreflightHandler extends BaseHandler
{
    private $response;

    private $headers = [];

    private $type;

    /**
     * CorsPreflightHandler constructor.
     * @param Response $response
     * @param array|string[][] $headers
     * @param string $type available values: xml, json, text
     */
    public function __construct(
        Response $response,
        array $headers = [
            'Access-Control-Allow-Headers' => [
                'Authorization',
                'X-Requested-With',
            ],
        ],
        $type = 'json'
    ) {
        parent::__construct();
        $this->response = $response;
        $this->headers = $headers;
        $this->type = $type;
    }

    public function handle($params)
    {
        foreach ($this->headers as $name => $values) {
            $values = is_array($values) ? $values : [$values];
            foreach ($values as $value) {
                $this->response->addHeader($name, $value);
            }
        }
        switch ($this->type) {
            case 'xml':
                return new XmlApiResponse(Response::S200_OK, '');
            case 'text':
                return new TextApiResponse(Response::S200_OK, '');
            case 'json':
            default:
                return new JsonApiResponse(Response::S200_OK, []);
        }
    }
}
