<?php

namespace Tomaj\NetteApi;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;

class Api
{
    private $endpoint;

    private $handler;

    private $authorization;

    public function __construct(EndpointInterface $endpoint, ApiHandlerInterface $handler, ApiAuthorizationInterface $authorization)
    {
        $this->endpoint = $endpoint;
        $this->handler = $handler;
        $this->authorization = $authorization;
    }

    public function getEndpoint(): EndpointInterface
    {
        return $this->endpoint;
    }

    public function getHandler(): ApiHandlerInterface
    {
        return $this->handler;
    }

    public function getAuthorization(): ApiAuthorizationInterface
    {
        return $this->authorization;
    }
}
