<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\ApiResponse;
use Tomaj\NetteApi\Params\InputParam;

interface ApiHandlerInterface
{
    /**
     * Returns available parameters that handler need
     *
     * @return InputParam[]
     */
    public function params();

    /**
     * Main handle method that will be executed when api
     * endpoint contected with this handler will be triggered
     *
     * @param array $params
     *
     * @return ApiResponse
     */
    public function handle($params);

    /**
     * Set actual endpoint identifier to hnadler.
     * It is neccesary for link creation.
     *
     * @param EndpointInterface $endpoint
     *
     * @return void
     */
    public function setEndpointIdentifier(EndpointInterface $endpoint);
}
