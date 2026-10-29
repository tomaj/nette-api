<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\DI;

use Nette\DI\InvalidConfigurationException;

/**
 * Collects endpoint definitions from every source (the api.endpoints list, discovered handlers)
 * and refuses a second definition of the same method + version + package + action.
 *
 * @internal used by NetteApiExtension at container compile time
 */
final class EndpointCollector
{
    /** @var list<EndpointDefinition> */
    private array $endpoints = [];

    /** @var array<string, string> endpoint => where it is defined */
    private array $sources = [];

    public function add(EndpointDefinition $endpoint): void
    {
        $key = $endpoint->describe();
        if (isset($this->sources[$key])) {
            throw new InvalidConfigurationException(sprintf(
                "Endpoint %s is defined twice: in %s and in %s. Remove one of them, or change the method, version, package or action of one so the URLs differ.",
                $endpoint->describe(),
                $this->sources[$key],
                $endpoint->source,
            ));
        }

        $this->sources[$key] = $endpoint->source;
        $this->endpoints[] = $endpoint;
    }

    /**
     * @return list<EndpointDefinition> in the order they were added
     */
    public function all(): array
    {
        return $this->endpoints;
    }
}
