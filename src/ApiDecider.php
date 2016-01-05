<?php

namespace Tomaj\NetteApi;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\Link\ApiLink;

// TODO - refactor - remove array with triplet, replace with some class

class ApiDecider
{
    /**
     * @var ApiHandlerInterface[]
     */
    private $handlers = [];

    /**
     * @var ApiLink
     */
    private $apiLink;

    /**
     * ApiDecider constructor.
     *
     * @param ApiLink $apiLink
     */
    public function __construct(ApiLink $apiLink)
    {
        $this->apiLink = $apiLink;
    }

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
     * @return Handlers\ApiHandlerInterface[]
     */
    public function getHandlers()
    {
        return $this->handlers;
    }

    /*
    public function getHandlersList($version)
    {
        $list = [];
        foreach ($this->getHandlers() as $handler) {
            $endpoint = $handler['endpoint'];
            if ($version && $version != $endpoint->getVersion()) {
                continue;
            }
            $item = [
                'method' => $endpoint->getMethod(),
                'version' => $endpoint->getVersion(),
                'package' => $endpoint->getPackage(),
                'api_action' => $endpoint->getApiAction(),
                'url' => $this->apiLink->link($endpoint),
            ];
            $params = $this->createParamsList($handler);
            if (count($params) > 0) {
                $item['params'] = $params;
            }
            $list[] = $item;
        }
        return $list;
    }
    
    private function createParamsList($handler)
    {
        $paramsList = $handler['handler']->params();
        $params = [];
        foreach ($paramsList as $param) {
            $parameter = [
                'type' => $param->getType(),
                'key' => $param->getKey(),
                'is_required' => $param->isRequired(),
            ];
            if ($param->getAvailableValues()) {
                $parameter['available_values'] = $param->getAvailableValues();
            }
            $params[] = $parameter;
        }
        return $params;
    }
    */
}
