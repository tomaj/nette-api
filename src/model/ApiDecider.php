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
    /** @var ApiHandlerInterface[] */
    private $handlers = [];

    /** @var ApiLink */
    private $apiLink;
    
    public function __construct(ApiLink $apiLink)
    {
        $this->apiLink = $apiLink;
    }

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

    public function addApiHandler(EndpointInterface $endpointIdentifier, ApiHandlerInterface $handler, ApiAuthorizationInterface $apiAuthorization)
    {
        $this->handlers[] = [
            'endpoint' => $endpointIdentifier,
            'handler' => $handler,
            'authorization' => $apiAuthorization,
        ];
        return $this;
    }

    public function getHandlers()
    {
        return $this->handlers;
    }
    
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
            if ($params) {
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
}
