<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

use Nette\DI\Container;
use Nette\Http\Response;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\Handlers\CorsPreflightHandlerInterface;

class ApiDecider
{
    /** @var Container */
    private $container;

    /** @var Api[] */
    private $apis = [];

    /** @var ApiHandlerInterface|null */
    private $globalPreflightHandler = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get api handler that match input method, version, package and apiAction.
     * If decider cannot find handler for given handler, returns defaults.
     *
     * @param string   $method
     * @param string   $version
     * @param string   $package
     * @param string   $apiAction
     *
     * @return Api
     */
    public function getApi(string $method, string $version, string $package, ?string $apiAction = null)
    {
        $method = strtoupper($method);
        $apiAction = $apiAction === '' ? null : $apiAction;

        foreach ($this->apis as $api) {
            $identifier = $api->getEndpoint();
            if ($method === $identifier->getMethod() && $identifier->getVersion() === $version && $identifier->getPackage() === $package && $identifier->getApiAction() === $apiAction) {
                $endpointIdentifier = new EndpointIdentifier($method, $version, $package, $apiAction);
                $handler = $this->getHandler($api);
                $handler->setEndpointIdentifier($endpointIdentifier);
                return new Api($api->getEndpoint(), $handler, $api->getAuthorization(), $api->getRateLimit());
            }
            if ($method === 'OPTIONS' && $this->globalPreflightHandler && $identifier->getVersion() === $version && $identifier->getPackage() === $package && $identifier->getApiAction() === $apiAction) {
                return new Api(new EndpointIdentifier('OPTIONS', $version, $package, $apiAction), $this->globalPreflightHandler, new NoAuthorization());
            }
        }
        return new Api(new EndpointIdentifier($method, $version, $package, $apiAction), new DefaultHandler(), new NoAuthorization());
    }

    public function enableGlobalPreflight(CorsPreflightHandlerInterface $corsHandler = null)
    {
        if (!$corsHandler) {
            $corsHandler = new CorsPreflightHandler(new Response());
        }
        $this->globalPreflightHandler = $corsHandler;
    }

    /**
     * Register new api handler
     *
     * @param EndpointInterface $endpointIdentifier
     * @param ApiHandlerInterface|string $handler
     * @param ApiAuthorizationInterface $apiAuthorization
     * @param RateLimitInterface|null $rateLimit
     * @return self
     */
    public function addApi(EndpointInterface $endpointIdentifier, $handler, ApiAuthorizationInterface $apiAuthorization, RateLimitInterface $rateLimit = null): self
    {
        $this->apis[] = new Api($endpointIdentifier, $handler, $apiAuthorization, $rateLimit);
        return $this;
    }

    /**
     * Get all registered apis
     *
     * @return Api[]
     */
    public function getApis(): array
    {
        $apis = [];
        foreach ($this->apis as $api) {
            $handler = $this->getHandler($api);
            $apis[] = new Api($api->getEndpoint(), $handler, $api->getAuthorization(), $api->getRateLimit());
        }
        return $apis;
    }

    private function getHandler(Api $api): ApiHandlerInterface
    {
        $handler = $api->getHandler();
        if (!is_string($handler)) {
            return $handler;
        }

        if (str_starts_with($handler, '@')) {
            return $this->container->getByName(substr($handler, 1));
        }

        return $this->container->getByType($handler);
    }
}
