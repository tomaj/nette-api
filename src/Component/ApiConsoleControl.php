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
use Tomaj\NetteApi\Misc\TypeHelper;

class ApiConsoleControl extends Control
{
    private readonly ApiConsoleFormFactoryInterface $formFactory;

    private ?FormRenderer $formRenderer = null;

    private ?string $templateFilePath = null;

    public function __construct(
        private readonly IRequest $request,
        private readonly EndpointInterface $endpoint,
        private readonly ApiHandlerInterface $handler,
        private readonly ApiAuthorizationInterface $authorization,
        private readonly ?ApiLink $apiLink = null,
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
        /** @phpstan-ignore-next-line */
        $form->onSuccess[] = $this->formSucceeded(...);
        return $form;
    }

    /**
     * @param Form $form
     * @param ArrayHash<mixed> $values
     */
    public function formSucceeded(Form $form, ArrayHash $values): void
    {
        $url = TypeHelper::toString($values['api_url'] ?? '');

        $token = null;
        if (isset($values['token'])) {
            $token = TypeHelper::toString($values['token']);
            unset($values['token']);
        }

        $method = TypeHelper::toString($values['api_method'] ?? '');
        unset($values['api_method']);

        $cookieFields = [];
        $getFields = [];
        $headers = [];
        if (isset($values['send_session_id']) && $values['send_session_id']) {
            $cookieFields[(string) session_name()] = session_id();
            session_write_close();
        }

        if (isset($values['custom_headers']) && $values['custom_headers']) {
            $headers = array_values(array_filter(array_map(trim(...), explode("\n", TypeHelper::toString($values['custom_headers'])))));
        }

        if ($this->authorization instanceof QueryApiKeyAuthentication) {
            $queryParamName = $this->authorization->getQueryParamName();
            $getFields[$queryParamName] = $values[$queryParamName] ?? null;
        } elseif ($this->authorization instanceof HeaderApiKeyAuthentication) {
            $headerName = $this->authorization->getHeaderName();
            $headers[] = $headerName . ':' . TypeHelper::toString($values['header_api_key'] ?? null);
        } elseif ($this->authorization instanceof CookieApiKeyAuthentication) {
            $cookieName = $this->authorization->getCookieName();
            $cookieFields[$cookieName] = $values['cookie_api_key'] ?? null;
        }

        $additionalValues = [
            'timeout' => is_numeric($values['timeout'] ?? null) ? (int) $values['timeout'] : ConsoleRequest::DEFAULT_TIMEOUT,
        ];
        if ($cookieFields !== []) {
            $additionalValues['cookieFields'] = $cookieFields;
        }
        if ($getFields !== []) {
            $additionalValues['getFields'] = $getFields;
        }
        if ($headers !== []) {
            $additionalValues['headers'] = $headers;
        }

        $consoleRequest = new ConsoleRequest($this->handler, $this->endpoint, $this->apiLink);
        $result = $consoleRequest->makeRequest($url, $method, $this->filterFormValues($this->formValuesToArray($values)), $additionalValues, $token);

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
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function filterFormValues(array $values): array
    {
        foreach ($this->handler->params() as $param) {
            $key = $param->getKey();
            if (($values['do_not_send_empty_value_for_' . $key] ?? null) === true && ($values[$key] ?? null) === '') {
                unset($values[$key]);
            }

            unset($values['do_not_send_empty_value_for_' . $key]);
        }

        return $values;
    }

    /**
     * @param ArrayHash<mixed> $values
     * @return array<string, mixed>
     */
    private function formValuesToArray(ArrayHash $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
