<?php

namespace Tomaj\NetteApi;

class EndpointIdentifier implements EndpointInterface
{
    private $method;

    private $version;

    private $package;

    private $apiAction;

    public function __construct($method, $version, $package, $apiAction)
    {
        $this->method = strtoupper($method);
        $this->version = $version;
        $this->package = $package;
        $this->apiAction = $apiAction;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getPackage()
    {
        return $this->package;
    }

    public function getApiAction()
    {
        if ($this->apiAction == '') {
            return null;
        }
        return $this->apiAction;
    }

    public function getUrl()
    {
        return "v{$this->version}/{$this->package}/{$this->apiAction}";
    }
}
