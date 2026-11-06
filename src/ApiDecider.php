<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Http\Response;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Handlers\CorsPreflightHandlerInterface;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\Input\EndpointTemplate;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\PathValues;
use Tomaj\NetteApi\RateLimit\AutowiredRateLimit;
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
     * @param string|null $requestPath absolute or application path, used to match {name} segments; static endpoints ignore it
     *
     * @return Api
     */
    public function getApi(string $method, string $version, string $package, ?string $apiAction = null, ?string $requestPath = null): Api
    {
        $method = strtoupper($method);
        $apiAction = $apiAction === '' ? null : $apiAction;
        PathValues::reset();

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

        $matched = $requestPath === null ? [] : $this->matchTemplate($method, $version, $requestPath);
        if (count($matched) > 1) {
            $urls = array_map(static fn (array $match): string => $match[0]->getEndpoint()->getUrl(), $matched);
            throw new \LogicException(sprintf(
                "%s request '%s' matches more than one endpoint (%s). Make a static segment differ so only one URL template matches.",
                $method,
                $requestPath,
                implode(', ', $urls),
            ));
        }
        if (count($matched) === 1) {
            [$api, $captured] = $matched[0];
            PathValues::set($captured);
            $handler = $this->getHandler($api);
            $handler->setEndpointIdentifier($api->getEndpoint());
            return new Api($api->getEndpoint(), $handler, $api->getAuthorization(), $api->getRateLimit());
        }

        if ($method === 'OPTIONS' && $this->globalPreflightHandler && $requestPath !== null && $this->matchTemplate('', $version, $requestPath) !== []) {
            return new Api(new EndpointIdentifier('OPTIONS', $version, $package, $apiAction), $this->globalPreflightHandler, new NoAuthorization());
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
     * @param RateLimiterInterface|RateLimitInterface|null $rateLimit a RateLimitInterface (deprecated) still works through LegacyRateLimitAdapter.
     *        Left out, the autowired RateLimitInterface service is used like in 3.x; null means no rate limit.
     * @throws \LogicException when it is left out and the container has more autowired RateLimitInterface services
     */
    public function addApi(EndpointInterface $endpointIdentifier, ApiHandlerInterface|string $handler, ApiAuthorizationInterface $apiAuthorization, RateLimiterInterface|RateLimitInterface|null $rateLimit = new AutowiredRateLimit()): self
    {
        if ($rateLimit instanceof AutowiredRateLimit) {
            // Nette DI does not autowire a union type, without this a limiter of 3.x would be dropped silently
            try {
                $rateLimit = $this->container->getByType(RateLimitInterface::class, false);
            } catch (MissingServiceException $e) {
                throw new \LogicException(sprintf(
                    'ApiDecider::addApi() for %s %s got no rate limit, so it looks for the autowired RateLimitInterface service like 3.x, but the container has more of them: %s. Pass the limiter as the 4th argument (null for no rate limit), or set autowired: false on all of them but one.',
                    $endpointIdentifier->getMethod(),
                    $endpointIdentifier->getUrl(),
                    implode(', ', $this->container->findByType(RateLimitInterface::class)),
                ), previous: $e);
            }
        }
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
        if (is_string($handler)) {
            if (str_starts_with($handler, '@')) {
                /**
                 * @var ApiHandlerInterface $resolved
                 */
                $resolved = $this->container->getByName(substr($handler, 1));
            } else {
                /**
                 * @var ApiHandlerInterface $resolved
                 * @var class-string<object> $handler
                 */
                $resolved = $this->container->getByType($handler);
            }
            $handler = $resolved;
        }

        $this->checkPath($api->getEndpoint(), $handler);
        return $handler;
    }

    /**
     * @return list<array{0: ApiHolder, 1: array<string, string>}>
     */
    private function matchTemplate(string $method, string $version, string $requestPath): array
    {
        $normalized = EndpointTemplate::normalize($requestPath, $version);
        if ($normalized === null) {
            return [];
        }

        $matched = [];
        foreach ($this->apis as $api) {
            $identifier = $api->getEndpoint();
            if (($method !== '' && $method !== $identifier->getMethod()) || !str_contains($identifier->getUrl(), '{')) {
                continue;
            }
            $captured = EndpointTemplate::match($identifier->getUrl(), $normalized);
            if ($captured !== null) {
                $matched[] = [$api, $captured];
            }
        }

        return $matched;
    }

    private function checkPath(EndpointInterface $endpoint, ApiHandlerInterface $handler): void
    {
        $names = [];
        foreach ($handler->params() as $param) {
            if ($param->getType() === InputParam::TYPE_PATH) {
                $names[] = $param->getKey();
            }
        }

        EndpointTemplate::assert($endpoint->getUrl(), $names, $handler::class);
    }
}
