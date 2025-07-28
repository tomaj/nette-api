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
use Tomaj\NetteApi\Misc\ArrayUtils;

final class ApiDecider
{
    /** @var Api[] */
    private array $apis = [];

    private ?ApiHandlerInterface $globalPreflightHandler = null;

    public function __construct(
        private readonly Container $container
    ) {
    }

    /**
     * Get api handler that match input method, version, package and apiAction.
     * If decider cannot find handler for given handler, returns defaults.
     */
    public function getApi(string $method, string $version, string $package, ?string $apiAction = null): Api
    {
        $method = strtoupper($method);
        $apiAction = $apiAction === '' ? null : $apiAction;

        // Use PHP 8.4's array_find to find matching API
        $matchingApi = array_find(
            $this->apis,
            fn(Api $api) => $this->isApiMatch($api, $method, $version, $package, $apiAction)
        );

        if ($matchingApi) {
            $endpointIdentifier = new EndpointIdentifier($method, $version, $package, $apiAction);
            $handler = $this->getHandler($matchingApi);
            
            if (method_exists($handler, 'setEndpointIdentifier')) {
                $handler->setEndpointIdentifier($endpointIdentifier);
            }
            
            return new Api($matchingApi->getEndpoint(), $handler, $matchingApi->getAuthorization(), $matchingApi->getRateLimit());
        }

        // Handle OPTIONS requests with global preflight handler
        if ($method === 'OPTIONS' && $this->globalPreflightHandler) {
            $optionsMatch = array_find(
                $this->apis,
                fn(Api $api) => $this->isEndpointMatch($api->getEndpoint(), $version, $package, $apiAction)
            );

            if ($optionsMatch) {
                return new Api(
                    new EndpointIdentifier('OPTIONS', $version, $package, $apiAction),
                    $this->globalPreflightHandler,
                    new NoAuthorization()
                );
            }
        }

        return new Api(
            new EndpointIdentifier($method, $version, $package, $apiAction),
            new DefaultHandler(),
            new NoAuthorization()
        );
    }

    public function enableGlobalPreflight(?CorsPreflightHandlerInterface $corsHandler = null): void
    {
        $this->globalPreflightHandler = $corsHandler ?? new CorsPreflightHandler(new Response());
    }

    /**
     * Register new api handler
     */
    public function addApi(
        EndpointInterface $endpointIdentifier,
        ApiHandlerInterface|string $handler,
        ApiAuthorizationInterface $apiAuthorization,
        ?RateLimitInterface $rateLimit = null
    ): self {
        $this->apis[] = new Api($endpointIdentifier, $handler, $apiAuthorization, $rateLimit);
        return $this;
    }

    /**
     * Get all registered APIs
     * 
     * @return Api[]
     */
    public function getApis(): array
    {
        return $this->apis;
    }

    /**
     * Check if any API exists for given version
     */
    public function hasApisForVersion(string $version): bool
    {
        return array_any(
            $this->apis,
            fn(Api $api) => $api->getEndpoint()->getVersion() === $version
        );
    }

    /**
     * Get all APIs for a specific version
     * 
     * @return Api[]
     */
    public function getApisForVersion(string $version): array
    {
        return array_filter(
            $this->apis,
            fn(Api $api) => $api->getEndpoint()->getVersion() === $version
        );
    }

    /**
     * Check if API matches the given criteria
     */
    private function isApiMatch(Api $api, string $method, string $version, string $package, ?string $apiAction): bool
    {
        $identifier = $api->getEndpoint();
        return $method === $identifier->getMethod()
            && $identifier->getVersion() === $version
            && $identifier->getPackage() === $package
            && $identifier->getApiAction() === $apiAction;
    }

    /**
     * Check if endpoint matches (used for OPTIONS requests)
     */
    private function isEndpointMatch(EndpointInterface $endpoint, string $version, string $package, ?string $apiAction): bool
    {
        return $endpoint->getVersion() === $version
            && $endpoint->getPackage() === $package
            && $endpoint->getApiAction() === $apiAction;
    }

    private function getHandler(Api $api): ApiHandlerInterface
    {
        $handler = $api->getHandler();
        
        if (is_string($handler)) {
            $handler = $this->container->getByType($handler);
        }
        
        if (!$handler instanceof ApiHandlerInterface) {
            throw new \InvalidArgumentException('Handler must implement ApiHandlerInterface');
        }
        
        return $handler;
    }
}
