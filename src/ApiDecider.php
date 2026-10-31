<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

use Nette\DI\Container;
use Nette\Http\Response;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Handlers\CorsPreflightHandlerInterface;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;

class ApiDecider
{
    /** @var list<ApiHolder> */
    private array $apis = [];

    private ?ApiHandlerInterface $globalPreflightHandler = null;

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * Get api handler that match input method, version, package and apiAction.
     * If decider cannot find handler for given handler, returns defaults.
     *
     * @param string   $apiAction
     *
     * @return Api
     */
    public function getApi(string $method, string $version, string $package, ?string $apiAction = null): Api
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

    public function enableGlobalPreflight(?CorsPreflightHandlerInterface $corsHandler = null): void
    {
        if (!$corsHandler && !$this->globalPreflightHandler) {
            $corsHandler = new CorsPreflightHandler(new Response());
        }

        if ($corsHandler) {
            $this->globalPreflightHandler = $corsHandler;
        }
    }

    /**
     * Register new api handler
     *
     * @param RateLimiterInterface|RateLimitInterface|null $rateLimit a RateLimitInterface (deprecated) still works through LegacyRateLimitAdapter
     */
    public function addApi(EndpointInterface $endpointIdentifier, ApiHandlerInterface|string $handler, ApiAuthorizationInterface $apiAuthorization, RateLimiterInterface|RateLimitInterface|null $rateLimit = null): self
    {
        $this->apis[] = new ApiHolder($endpointIdentifier, $handler, $apiAuthorization, $rateLimit);
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

    /**
     * The authorizations of all registered apis, without creating the handlers
     *
     * @return list<ApiAuthorizationInterface>
     */
    public function getAuthorizations(): array
    {
        return array_map(static fn (ApiHolder $api): ApiAuthorizationInterface => $api->getAuthorization(), $this->apis);
    }

    private function getHandler(ApiHolder $api): ApiHandlerInterface
    {
        $handler = $api->getHandler();
        if (!is_string($handler)) {
            return $handler;
        }

        if (str_starts_with($handler, '@')) {
            /**
             * @var ApiHandlerInterface $apiHandler
             */
            $apiHandler = $this->container->getByName(substr($handler, 1));
            return $apiHandler;
        }

        /**
         * @var ApiHandlerInterface $apiHandler
         * @var class-string<object> $handler
         */
        $apiHandler = $this->container->getByType($handler);
        return $apiHandler;
    }
}
