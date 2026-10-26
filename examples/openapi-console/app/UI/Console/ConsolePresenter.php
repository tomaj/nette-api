<?php

declare(strict_types=1);

namespace App\UI\Console;

use Nette\Application\UI\Presenter;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Component\ApiConsoleControl;
use Tomaj\NetteApi\Component\ApiListingControl;
use Tomaj\NetteApi\Link\ApiLink;

/**
 * The web console: a list of all endpoints and a generated form to call one.
 * Protect it (or leave it out) in production, it can call every endpoint.
 */
final class ConsolePresenter extends Presenter
{
    private string $method = '';
    private string $version = '';
    private string $package = '';
    private ?string $apiAction = null;

    public function __construct(private ApiDecider $apiDecider, private ApiLink $apiLink)
    {
        parent::__construct();
    }

    public function actionShow(string $method, string $version, string $package, ?string $apiAction = null): void
    {
        $this->method = $method;
        $this->version = $version;
        $this->package = $package;
        $this->apiAction = $apiAction;
        $this->template->endpoint = $this->apiDecider->getApi($method, $version, $package, $apiAction)->getEndpoint();
    }

    protected function createComponentApiListing(): ApiListingControl
    {
        $listing = new ApiListingControl($this->apiDecider);
        $listing->onClick[] = function (string $method, string $version, string $package, ?string $apiAction): void {
            $this->redirect('show', $method, $version, $package, $apiAction);
        };
        return $listing;
    }

    protected function createComponentApiConsole(): ApiConsoleControl
    {
        $api = $this->apiDecider->getApi($this->method, $this->version, $this->package, $this->apiAction);
        return new ApiConsoleControl(
            $this->getHttpRequest(),
            $api->getEndpoint(),
            $api->getHandler(),
            $api->getAuthorization(),
            $this->apiLink,
        );
    }
}
