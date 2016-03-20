<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Params\InputParam;
use Nette\Application\IResponse;

interface ApiHandlerInterface
{
    /**
     * Description of handler
     * @return string
     */
    public function description();
    
    /**
     * Returns available parameters that handler need
     *
     * @return InputParam[]
     */
    public function params();

    /**
     * Returns list of tags for handler
     * @return []
     */
    public function tags();
    
    /**
     * Main handle method that will be executed when api
     * endpoint contected with this handler will be triggered
     *
     * @param array $params
     *
     * @return IResponse
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
