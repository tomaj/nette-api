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
    private const array METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

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
        /** @var string|Statement|list<string>|array<string, mixed>|false|null a list of names, or an inline limit {algorithm, limit, interval, key, name} */
        public string|Statement|array|false|null $rateLimit,
        public string $source,
    ) {
        $this->method = strtoupper(trim($method));
        $this->version = (string) $version;
        $this->action = $action === '' ? null : $action;

        if (preg_match('~^[A-Z]+$~', $this->method) !== 1) {
            throw new InvalidConfigurationException(sprintf(
                "Endpoint in %s has an invalid method '%s', a method is one word of letters. Use an HTTP method, for example %s.",
                $source,
                $method,
                $this->example('method', "'GET'", 'method: GET'),
            ));
        }
        $didYouMean = Hint::didYouMean($this->method, self::METHODS);
        if ($didYouMean !== '' && !in_array($this->method, self::METHODS, true)) {
            throw new InvalidConfigurationException(sprintf(
                "Endpoint in %s has method '%s', which looks like a typo.%s Use an HTTP method (%s), for example %s.",
                $source,
                $method,
                $didYouMean,
                implode(', ', self::METHODS),
                $this->example('method', "'GET'", 'method: GET'),
            ));
        }
        if ($this->version === '') {
            throw $this->invalid('version', '', 'it is empty', $this->example('version', '1', 'version: 1'));
        }
        if (str_starts_with(strtolower($this->version), 'v')) {
            $fixed = substr($this->version, 1);
            throw $this->invalid('version', $this->version, sprintf("the 'v' is added to the URL automatically (/v%s/%s)", $fixed, $package), $this->example('version', $this->quoteVersion($fixed), 'version: ' . $this->quoteVersion($fixed)));
        }
        if (str_contains($this->version, '/')) {
            throw $this->invalid('version', $this->version, 'a version is one URL segment without a slash', $this->example('version', "'1.1'", "version: '1.1'"));
        }
        foreach (['version' => $this->version, 'package' => $package, 'action' => $this->action ?? ''] as $key => $value) {
            if (preg_match('~[\s?#]~', $value) === 1) {
                throw $this->invalid($key, $value, 'it contains a space, ? or #, which cannot be in a URL path', match ($key) {
                    'version' => $this->example('version', '1', 'version: 1'),
                    'package' => $this->example('package', "'users'", 'package: users'),
                    default => $this->example('action', "'detail'", 'action: detail'),
                });
            }
        }
        if ($package === '') {
            throw $this->invalid('package', '', 'it is empty', $this->example('package', "'users'", 'package: users'));
        }
        if (str_contains($package, '/')) {
            [$first, $rest] = explode('/', $package, 2);
            throw $this->invalid('package', $package, 'a package is one URL segment without a slash', str_contains($rest, '/') || $first === '' || $rest === ''
                ? $this->example('package', "'users'", 'package: users')
                : $this->example('package', sprintf("'%s', '%s'", $first, $rest), sprintf('package: %s, action: %s', $first, $rest)));
        }
        if ($this->action !== null && str_contains($this->action, '/')) {
            throw $this->invalid('action', $this->action, 'an action is one URL segment without a slash', $this->example('action', "'detail'", 'action: detail'));
        }
    }

    private function invalid(string $key, string $value, string $reason, string $example): InvalidConfigurationException
    {
        return new InvalidConfigurationException(sprintf(
            "Endpoint in %s has an invalid %s '%s', %s. Use for example %s.",
            $this->source,
            $key,
            $value,
            $reason,
            $example,
        ));
    }

    /**
     * The fix written the way of the source: "version: 1" in neon, "#[Get(1, ...)]" for an attribute
     */
    private function example(string $key, string $attributeValue, string $neon): string
    {
        if (preg_match('~#\[(\w+)\]~', $this->source, $m) === 1) {
            $arguments = match ($key) {
                'method' => sprintf("%s, 1, 'users'", $attributeValue),
                'version' => sprintf("%s, 'users'", $attributeValue),
                'package' => sprintf('1, %s', $attributeValue),
                default => sprintf("1, 'users', %s", $attributeValue),
            };
            return sprintf('#[%s(%s)]', $key === 'method' ? 'Endpoint' : $m[1], $arguments);
        }

        return $neon;
    }

    private function quoteVersion(string $version): string
    {
        return preg_match('~^\d+$~', $version) === 1 ? $version : "'" . $version . "'";
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
