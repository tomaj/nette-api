<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

use InvalidArgumentException;

final class EndpointIdentifier implements EndpointInterface
{
    /**
     * @param string $method example: "GET", "POST", "PUT", "DELETE"
     * @param string|int $version Version must have semantic numbering. For example "1", "1.1", "0.13.2" etc.
     * @param string $package example: "users"
     * @param string|null $apiAction example: "query"
     */
    public function __construct(
        private string $method,
        private string|int $version,
        private readonly string $package,
        private readonly ?string $apiAction = null
    ) {
        $version = (string) $version;
        $this->method = strtoupper($method);
        if (str_contains($version, '/')) {
            throw new InvalidArgumentException('Version must have semantic numbering. For example "1", "1.1", "0.13.2" etc.');
        }
        $this->version = $version;
    }

    #[\Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    #[\Override]
    public function getVersion(): string
    {
        return (string) $this->version;
    }

    #[\Override]
    public function getPackage(): string
    {
        return $this->package;
    }

    #[\Override]
    public function getApiAction(): ?string
    {
        if ($this->apiAction === '') {
            return null;
        }

        return $this->apiAction;
    }

    #[\Override]
    public function getUrl(): string
    {
        return sprintf('v%s/%s/%s', $this->version, $this->package, $this->apiAction);
    }
}
