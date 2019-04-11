<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Params\ParamInterface;
use Tomaj\NetteApi\Response\ResponseInterface;

interface ApiHandlerInterface
{
    /**
     * Returns available parameters that handler need
     *
     * @return ParamInterface[]
     */
    public function params(): array;

    /**
     * Main handle method that will be executed when api
     * endpoint contected with this handler will be triggered
     *
     * @param array $params
     *
     * @return ResponseInterface
     */
    public function handle(array $params): ResponseInterface;

    /**
     * Set actual endpoint identifier to hnadler.
     * It is neccesary for link creation.
     *
     * @param EndpointInterface $endpoint
     *
     * @return void
     */
    public function setEndpointIdentifier(EndpointInterface $endpoint): void;
}
