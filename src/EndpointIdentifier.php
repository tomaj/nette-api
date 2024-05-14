<?php

declare(strict_types=1);

namespace Tomaj\NetteApi;

use InvalidArgumentException;

class EndpointIdentifier implements EndpointInterface
{
    private $method;

    private $version;

    private $package;

    private $apiAction;

    public function __construct(string $method, string|int $version, string $package, ?string $apiAction = null)
    {
        $version = (string) $version;
        $this->method = strtoupper($method);
        if (strpos($version, '/') !== false) {
            throw new InvalidArgumentException('Version must have semantic numbering. For example "1", "1.1", "0.13.2" etc.');
        }
        $this->version = $version;
        $this->package = $package;
        $this->apiAction = $apiAction;
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
