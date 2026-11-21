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
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
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

    /** @var array<int, true> holders whose URL template was checked against the path params of the handler */
    private array $checked = [];

    private ?ApiHandlerInterface $globalPreflightHandler = null;

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * Get api handler that match input method, version, package and apiAction.
     * If decider cannot find handler for given handler, returns defaults.
     *
     * An endpoint with {name} segments is found here only by its literal URL, for example by the API console;
     * a request with the values in the path is matched by matchPath().
     *
     * @param string   $apiAction
     *
     * @return Api
     */
    public function getApi(string $method, string $version, string $package, ?string $apiAction = null): Api
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

        return new Api(new EndpointIdentifier($method, $version, $package, $apiAction), new DefaultHandler(), new NoAuthorization());
    }

    /**
     * Finds an endpoint whose URL has {name} segments, for example users/{id}/detail, and stores the matched values
     * for the path params. Takes the values the router parsed: $params are the segments after the action
     * (the <params> of the route). Null when no template matches.
     */
    public function matchPath(string $method, string $version, string $package, ?string $apiAction, ?string $params = null): ?Api
    {
        $method = strtoupper($method);
        $apiAction = $apiAction === '' ? null : $apiAction;
        $path = implode('/', array_filter([$package, $apiAction, $params], static fn (?string $part): bool => $part !== null && $part !== ''));
        $matched = $this->matchTemplate($method, $version, $path);
        if ($matched !== null) {
            [$api, $captured] = $matched;
            PathValues::set($captured);
            $handler = $this->getHandler($api);
            $handler->setEndpointIdentifier($api->getEndpoint());
            return new Api($api->getEndpoint(), $handler, $api->getAuthorization(), $api->getRateLimit());
        }

        if ($method === 'OPTIONS' && $this->globalPreflightHandler && $this->matchTemplate('', $version, $path) !== null) {
            return new Api(new EndpointIdentifier('OPTIONS', $version, $package, $apiAction), $this->globalPreflightHandler, new NoAuthorization());
        }

        return null;
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
     * @throws \LogicException when it is left out and the container has more autowired RateLimitInterface services,
     *         or when the URL has a malformed {name} segment or can match the same request as another URL template
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
        $url = $endpointIdentifier->getUrl();
        if (str_contains($url, '{')) {
            EndpointTemplate::assertSyntax($url, sprintf('%s %s', $endpointIdentifier->getMethod(), $url));
            foreach ($this->apis as $api) {
                if ($api->getEndpoint()->getMethod() === $endpointIdentifier->getMethod()) {
                    EndpointTemplate::assertDisjoint([$endpointIdentifier->getMethod() => [$api->getEndpoint()->getUrl(), $url]]);
                }
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

        $id = spl_object_id($api);
        if (!isset($this->checked[$id]) && $this->checkPath($api->getEndpoint(), $handler)) {
            // the configuration does not change, so it is checked once and not on every request
            $this->checked[$id] = true;
        }
        return $handler;
    }

    /**
     * @return array{0: ApiHolder, 1: array<string, string>}|null
     */
    private function matchTemplate(string $method, string $version, string $path): ?array
    {
        foreach ($this->apis as $api) {
            $identifier = $api->getEndpoint();
            if (($method !== '' && $method !== $identifier->getMethod()) || $identifier->getVersion() !== $version || !str_contains($identifier->getUrl(), '{')) {
                continue;
            }
            // addApi() refuses two templates that can match one request, so the first match is the only one
            $captured = EndpointTemplate::match(sprintf('%s/%s', $identifier->getPackage(), $identifier->getApiAction() ?? ''), $path);
            if ($captured !== null) {
                return [$api, $captured];
            }
        }

        return null;
    }

    /**
     * A URL template must match the path params of its handler, and a path param needs a {name} in the URL.
     * False when the params of a plain handler cannot be read now; the presenter answers that error after the authorization.
     */
    private function checkPath(EndpointInterface $endpoint, ApiHandlerInterface $handler): bool
    {
        if ($handler instanceof TypedHandler) {
            // from the compiled metadata, without building every param and its OpenAPI schema
            $names = DtoMetadataRegistry::forHandler($handler::class)->pathNames();
        } else {
            try {
                $params = $handler->params();
            } catch (\Throwable) {
                return false;
            }
            $names = [];
            foreach ($params as $param) {
                if ($param->getType() === InputParam::TYPE_PATH) {
                    $names[] = $param->getKey();
                }
            }
        }

        EndpointTemplate::assert($endpoint->getUrl(), $names, $handler::class);
        return true;
    }
}
