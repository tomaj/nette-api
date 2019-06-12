<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Nette\Bridges\ApplicationLatte\Template;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Api;

/**
 * @method void onClick(string $method, int $version, string $package, ?string $apiAction)
 */
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

        /** @var Template $template */
        $template = $this->getTemplate();
        $template->add('apis', $this->groupApis($apis));
        $template->setFile(__DIR__ . '/api_listing.latte');
        $template->render();
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
