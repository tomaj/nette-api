<?php

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Api;

class ApiListingControl extends Control
{
    /** @var ApiDecider */
    private $apiDecider;

    public $onClick = [];

    public function __construct(ApiDecider $apiDecider)
    {
        $this->apiDecider = $apiDecider;
    }

    public function render(): void
    {
        $apis = $this->apiDecider->getApis();
        $this->getTemplate()->add('apis', $this->groupApis($apis));
        $this->getTemplate()->setFile(__DIR__ . '/api_listing.latte');
        $this->getTemplate()->render();
    }

    public function handleSelect(string $method, int $version, string $package, ?string $apiAction = null): void
    {
        $this->onClick($method, $version, $package, $apiAction);
    }

    /**
     * @param Api[] $handlers
     * @return array
     */
    private function groupApis(array $handlers): array
    {
        $versionHandlers = [];
        foreach ($handlers as $handler) {
            $endPoint = $handler->getEndpoint();
            if (!isset($versionHandlers[$endPoint->getVersion()])) {
                $versionHandlers[$endPoint->getVersion()] = [];
            }
            $versionHandlers[$endPoint->getVersion()][] = $handler;
        }
        return $versionHandlers;
    }
}
