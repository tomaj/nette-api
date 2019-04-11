<?php

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Nette\ComponentModel\IContainer;
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

    public function __construct(IContainer $parent, $name, ApiDecider $apiDecider)
    {
        $this->apiDecider = $apiDecider;
    }

    public function onClick(Closure $callback)
    {
        $this->clickCallback = $callback;
    }

    public function render()
    {
        $handlers = $this->apiDecider->getApis();
        $this->getTemplate()->add('handlers', $this->sortHandlers($handlers));
        $this->getTemplate()->setFile(__DIR__ . '/api_listing.latte');
        $this->getTemplate()->render();
    }

    public function handleSelect($method, $version, $package, $apiAction)
    {
        if (!$this->clickCallback) {
            throw new Exception('You have to set onClick callback to component!');
        }

        $this->clickCallback->__invoke($method, $version, $package, $apiAction);
    }

    /**
     * @param Api[] $handlers
     * @return Api[]
     */
    private function sortHandlers($handlers)
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
