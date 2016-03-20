<?php

namespace Tomaj\NetteApi;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Handlers\DefaultHandler;

class ApiDecider
{
    /** @var ApiHandlerInterface[] */
    private $handlers = [];

    /** @var string */
    private $title;
    
    /** @var string */
    private $description;
    
    /**
     * Get api handler that match input method, version, package and apiAction.
     * If decider cannot find handler for given handler, returns defaults.
     *
     * @param string   $method
     * @param integer  $version
     * @param string   $package
     * @param string   $apiAction
     *
     * @return array
     */
    public function getApiHandler($method, $version, $package, $apiAction = '')
    {
        foreach ($this->handlers as $handler) {
            $identifier = $handler['endpoint'];
            if ($method == $identifier->getMethod() && $identifier->getVersion() == $version && $identifier->getPackage() == $package && $identifier->getApiAction() == $apiAction) {
                $endpointIdentifier = new EndpointIdentifier($method, $version, $package, $apiAction);
                $handler['handler']->setEndpointIdentifier($endpointIdentifier);
                return $handler;
            }
        }
        return [
            'endpoint' => new EndpointIdentifier($method, $version, $package, $apiAction),
            'authorization' => new NoAuthorization(),
            'handler' => new DefaultHandler($version, $package, $apiAction)
        ];
    }

    /**
     * Register new api handler
     *
     * @param EndpointInterface         $endpointIdentifier
     * @param ApiHandlerInterface       $handler
     * @param ApiAuthorizationInterface $apiAuthorization
     *
     * @return $this
     */
    public function addApiHandler(EndpointInterface $endpointIdentifier, ApiHandlerInterface $handler, ApiAuthorizationInterface $apiAuthorization)
    {
        $this->handlers[] = [
            'endpoint' => $endpointIdentifier,
            'handler' => $handler,
            'authorization' => $apiAuthorization,
        ];
        return $this;
    }

    /**
     * Get all registered handlers
     *
     * @return ApiHandlerInterface[]
     */
    public function getHandlers()
    {
        return $this->handlers;
    }
    
    /**
     * @param string $title
     * @return ApiDecider
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $description
     * @return ApiDecider
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}
