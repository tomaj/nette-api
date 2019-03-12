<?php

namespace Tomaj\NetteApi;

use Nette\Http\Response;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Handlers\DefaultHandler;

class ApiDecider
{
    /**
     * @var ApiHandlerInterface[]
     */
    private $handlers = [];

    /**
     * @var ApiHandlerInterface
     */
    private $globalPreflightHandler = null;

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
            if ($method == 'OPTIONS' && $this->globalPreflightHandler && $identifier->getVersion() == $version && $identifier->getPackage() == $package && $identifier->getApiAction() == $apiAction) {
                return [
                    'endpoint' => new EndpointIdentifier('OPTION', $version, $package, $apiAction),
                    'authorization' => new NoAuthorization(),
                    'handler' => $this->globalPreflightHandler,
                ];
            }
        }
        return [
            'endpoint' => new EndpointIdentifier($method, $version, $package, $apiAction),
            'authorization' => new NoAuthorization(),
            'handler' => new DefaultHandler()
        ];
    }

    public function enableGlobalPreflight(ApiHandlerInterface $corsHandler = null)
    {
        if (!$corsHandler) {
            $corsHandler = new CorsPreflightHandler(new Response());
        }
        $this->globalPreflightHandler = $corsHandler;
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
}
