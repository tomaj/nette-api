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
    /** @var Api[] */
    private $apis = [];

    /** @var ApiHandlerInterface|null */
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
     * @return Api
     */
    public function getApi(string $method, int $version, string $package, ?string $apiAction = null)
    {
        $method = strtoupper($method);
        $apiAction = $apiAction === '' ? null : $apiAction;

        foreach ($this->apis as $api) {
            $identifier = $api->getEndpoint();
            if ($method === $identifier->getMethod() && $identifier->getVersion() === $version && $identifier->getPackage() === $package && $identifier->getApiAction() === $apiAction) {
                $endpointIdentifier = new EndpointIdentifier($method, $version, $package, $apiAction);
                $api->getHandler()->setEndpointIdentifier($endpointIdentifier);
                return $api;
            }
            if ($method === 'OPTIONS' && $this->globalPreflightHandler && $identifier->getVersion() === $version && $identifier->getPackage() === $package && $identifier->getApiAction() === $apiAction) {
                return new Api(new EndpointIdentifier('OPTIONS', $version, $package, $apiAction), $this->globalPreflightHandler, new NoAuthorization());
            }
        }
        return new Api(new EndpointIdentifier($method, $version, $package, $apiAction), new DefaultHandler(), new NoAuthorization());
    }

    public function enableGlobalPreflight(ApiHandlerInterface $corsHandler = null)
    {
        if (!$corsHandler) {
            $corsHandler = new CorsPreflightHandler(new Response());
        }
        $this->globalPreflightHandler = $corsHandler;
    }

    /**
     * @deprecated use addApi instead
     */
    public function addApiHandler(EndpointInterface $endpointIdentifier, ApiHandlerInterface $handler, ApiAuthorizationInterface $apiAuthorization)
    {
        return $this->addApi($endpointIdentifier, $handler, $apiAuthorization);
    }

    /**
     * Register new api handler
     *
     * @param EndpointInterface $endpointIdentifier
     * @param ApiHandlerInterface $handler
     * @param ApiAuthorizationInterface $apiAuthorization
     * @return self
     */
    public function addApi(EndpointInterface $endpointIdentifier, ApiHandlerInterface $handler, ApiAuthorizationInterface $apiAuthorization): self
    {
        $this->apis[] = new Api($endpointIdentifier, $handler, $apiAuthorization);
        return $this;
    }

    /**
     * Get all registered apis
     *
     * @return Api[]
     */
    public function getApis(): array
    {
        return $this->apis;
    }
}
