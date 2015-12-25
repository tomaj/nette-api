<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\EndpointInterface;

interface ApiHandlerInterface
{
    public function params();

    public function handle($params);

    public function setEndpointIdentifier(EndpointInterface $endpoint);
}
