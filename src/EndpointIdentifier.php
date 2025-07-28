<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

use InvalidArgumentException;

readonly class EndpointIdentifier implements EndpointInterface
{
    public readonly string $method {
        get => strtoupper($this->method);
    }

    public readonly string $url {
        get => "v{$this->version}/{$this->package}/{$this->apiAction}";
    }

    public readonly ?string $normalizedApiAction {
        get => $this->apiAction === '' ? null : $this->apiAction;
    }

    /**
     * @param string $method example: "GET", "POST", "PUT", "DELETE"
     * @param string|int $version Version must have semantic numbering. For example "1", "1.1", "0.13.2" etc.
     * @param string $package example: "users"
     * @param string|null $apiAction example: "query"
     */
    public function __construct(
        string $method,
        string|int $version,
        public readonly string $package,
        public readonly ?string $apiAction = null
    ) {
        $this->method = $method;
        $version = (string) $version;
        
        if (str_contains($version, '/')) {
            throw new InvalidArgumentException('Version must have semantic numbering. For example "1", "1.1", "0.13.2" etc.');
        }
        
        $this->version = $version;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function getApiAction(): ?string
    {
        return $this->normalizedApiAction;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
