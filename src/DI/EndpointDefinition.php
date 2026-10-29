<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\DI;

use Nette\DI\Definitions\Statement;
use Nette\DI\InvalidConfigurationException;

/**
 * One endpoint as written in neon (api.endpoints) or found by discovery, before it becomes an addApi() call.
 *
 * Authorization and rate limit: null means "use api.defaults", false means "none".
 * Another extension can pass it to NetteApiExtension::addEndpoint() in its loadConfiguration().
 */
final readonly class EndpointDefinition
{
    public string $method;

    public string $version;

    public ?string $action;

    public function __construct(
        string $method,
        string|int $version,
        public string $package,
        ?string $action,
        public string|Statement $handler,
        public string|Statement|false|null $authorization,
        public string|Statement|false|null $rateLimit,
        public string $source,
    ) {
        $this->method = strtoupper(trim($method));
        $this->version = (string) $version;
        $this->action = $action === '' ? null : $action;

        if (preg_match('~^[A-Z]+$~', $this->method) !== 1) {
            throw new InvalidConfigurationException(sprintf(
                "Endpoint in %s has an invalid method '%s'. Use an HTTP method like GET or POST.",
                $source,
                $method,
            ));
        }
        if ($this->version === '' || str_contains($this->version, '/') || str_starts_with(strtolower($this->version), 'v')) {
            throw new InvalidConfigurationException(sprintf(
                "Endpoint in %s has an invalid version '%s'. Use a version without a slash and without the 'v' prefix, for example 1 or '1.1'.",
                $source,
                $this->version,
            ));
        }
        if ($package === '' || str_contains($package, '/')) {
            throw new InvalidConfigurationException(sprintf(
                "Endpoint in %s has an invalid package '%s'. Use a package without a slash, for example users.",
                $source,
                $package,
            ));
        }
        if ($this->action !== null && str_contains($this->action, '/')) {
            throw new InvalidConfigurationException(sprintf(
                "Endpoint in %s has an invalid action '%s'. Use an action without a slash, for example detail.",
                $source,
                $this->action,
            ));
        }
    }

    public function describe(): string
    {
        return $this->method . ' /' . $this->path();
    }

    private function path(): string
    {
        return 'v' . $this->version . '/' . $this->package . ($this->action === null ? '' : '/' . $this->action);
    }
}
