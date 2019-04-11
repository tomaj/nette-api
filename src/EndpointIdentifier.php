<?php

namespace Tomaj\NetteApi;

class EndpointIdentifier implements EndpointInterface
{
    private $method;

    private $version;

    private $package;

    private $apiAction;

    public function __construct(string $method, int $version, string $package, ?string $apiAction = null)
    {
        $this->method = strtoupper($method);
        $this->version = $version;
        $this->package = $package;
        $this->apiAction = $apiAction;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function getApiAction(): ?string
    {
        if ($this->apiAction === '') {
            return null;
        }
        return $this->apiAction;
    }

    public function getUrl(): string
    {
        return "v{$this->version}/{$this->package}/{$this->apiAction}";
    }
}
