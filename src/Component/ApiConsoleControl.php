<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Forms\FormRenderer;
use Nette\Http\IRequest;
use Nette\Utils\ArrayHash;
use Tomaj\Form\Renderer\BootstrapVerticalRenderer;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Misc\ConsoleRequest;

class ApiConsoleControl extends Control
{
    private ApiConsoleFormFactoryInterface $formFactory;

    private ?FormRenderer $formRenderer = null;

    private ?string $templateFilePath = null;

    public function __construct(
        private IRequest $request,
        private EndpointInterface $endpoint,
        private ApiHandlerInterface $handler,
        private ApiAuthorizationInterface $authorization,
        private ?ApiLink $apiLink = null,
        ?ApiConsoleFormFactoryInterface $formFactory = null
    ) {
        $this->formFactory = $formFactory ?: new DefaultApiConsoleFormFactory();
    }

    public function render(): void
    {
        /** @var DefaultTemplate $template */
        $template = $this->getTemplate();
        $template->setFile($this->getTemplateFilePath());
        $template->add('handler', $this->handler);
        $template->render();
    }

    protected function createComponentConsoleForm(): Form
    {
        $form = $this->formFactory->create($this->request, $this->endpoint, $this->handler, $this->authorization, $this->apiLink);
        $form->setRenderer($this->getFormRenderer());
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, ArrayHash $values): void
    {
        $url = $values['api_url'];

        $token = null;
        if (isset($values['token'])) {
            $token = $values['token'];
            unset($values['token']);
        }

        $method = $values['api_method'];
        unset($values['api_method']);

        $additionalValues = [];
        if (isset($values['send_session_id']) && $values['send_session_id']) {
            $additionalValues['cookieFields'][session_name()] = session_id();
            session_write_close();
        }

        if (isset($values['custom_headers']) && $values['custom_headers']) {
            $additionalValues['headers'] = array_filter(array_map('trim', explode("\n", $values['custom_headers'])));
        }

        $additionalValues['timeout'] = $values['timeout'];

        if ($this->authorization instanceof QueryApiKeyAuthentication) {
            $queryParamName = $this->authorization->getQueryParamName();
            $additionalValues['getFields'][$queryParamName] = $values[$queryParamName] ?? null;
        } elseif ($this->authorization instanceof HeaderApiKeyAuthentication) {
            $headerName = $this->authorization->getHeaderName();
            $additionalValues['headers'][] = $headerName . ':' . ($values['header_api_key'] ?? null);
        } elseif ($this->authorization instanceof CookieApiKeyAuthentication) {
            $cookieName = $this->authorization->getCookieName();
            $additionalValues['cookieFields'][$cookieName] = $values['cookie_api_key'] ?? null;
        }

        $consoleRequest = new ConsoleRequest($this->handler, $this->endpoint, $this->apiLink);
        $result = $consoleRequest->makeRequest($url, $method, $this->filterFormValues((array) $values), $additionalValues, $token);

        /** @var DefaultTemplate $template */
        $template = $this->getTemplate();
        $template->add('response', $result);

        if ($this->getPresenter()->isAjax()) {
            $this->getPresenter()->redrawControl();
        }
    }

    public function setFormRenderer(FormRenderer $formRenderer): void
    {
        $this->formRenderer = $formRenderer;
    }

    private function getFormRenderer(): FormRenderer
    {
        return $this->formRenderer ?: new BootstrapVerticalRenderer();
    }

    public function setTemplateFilePath(string $templateFilePath): void
    {
        $this->templateFilePath = $templateFilePath;
    }

    private function getTemplateFilePath(): string
    {
        return $this->templateFilePath ?: __DIR__ . '/console.latte';
    }

    /**
     * @param mixed[] $values
     * @return mixed[]
    */
    private function filterFormValues(array $values): array
    {
        foreach ($this->handler->params() as $param) {
            $key = $param->getKey();
            if ($values['do_not_send_empty_value_for_' . $key] === true && $values[$key] === '') {
                unset($values[$key]);
            }

            unset($values['do_not_send_empty_value_for_' . $key]);
        }

        return $values;
    }
}
