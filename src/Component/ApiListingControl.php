<?php

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Tomaj\NetteApi\ApiDecider;
use Closure;
use Exception;
use Tomaj\NetteApi\Api;

class ApiListingControl extends Control
{
    /** @var ApiDecider */
    private $apiDecider;

    /** @var Closure|null */
    private $clickCallback;

    public function __construct(ApiDecider $apiDecider)
    {
        $this->apiDecider = $apiDecider;
    }

    public function onClick(Closure $callback): void
    {
        $this->clickCallback = $callback;
    }

    public function render(): void
    {
        $apis = $this->apiDecider->getApis();
        $this->getTemplate()->add('apis', $this->groupApis($apis));
        $this->getTemplate()->setFile(__DIR__ . '/api_listing.latte');
        $this->getTemplate()->render();
    }

    public function handleSelect(string $method, int $version, string $package, ?string $apiAction = null)
    {
        if (!$this->clickCallback) {
            throw new Exception('You have to set onClick callback to component!');
        }

        $this->clickCallback->__invoke($method, $version, $package, $apiAction);
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
