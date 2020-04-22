<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Http\IRequest;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Misc\ConsoleRequest;

class ApiConsoleControl extends Control
{
    private $request;

    private $endpoint;

    private $handler;

    private $authorization;

    public function __construct(IRequest $request, EndpointInterface $endpoint, ApiHandlerInterface $handler, ApiAuthorizationInterface $authorization)
    {
        $this->request = $request;
        $this->endpoint = $endpoint;
        $this->handler = $handler;
        $this->authorization = $authorization;
    }

    public function render(): void
    {
        /** @var Template $template */
        $template = $this->getTemplate();
        $template->setFile(__DIR__ . '/console.latte');
        $template->add('handler', $this->handler);
        $template->render();
    }

    protected function createComponentConsoleForm(): Form
    {
        $form = new Form();

        $defaults = [];

        $form->setRenderer(new BootstrapRenderer());

        $uri = $this->request->getUrl();
        $scheme = $uri->scheme;
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }
        $port = '';
        if ($uri->scheme === 'http' && $uri->port !== 80) {
            $port = ':' . $uri->port;
        }
        $url = $scheme . '://' . $uri->host . $port . '/api/' . $this->endpoint->getUrl();

        $form->addText('api_url', 'Api Url');
        $defaults['api_url'] = $url;

        $form->addText('api_method', 'Method');
        $defaults['api_method'] = $this->endpoint->getMethod();

        if ($this->authorization instanceof BearerTokenAuthorization) {
            $form->addText('token', 'Token')
                ->setHtmlAttribute('placeholder', 'Enter token');
        } elseif ($this->authorization instanceof NoAuthorization) {
            $form->addText('authorization', 'Authorization')
                ->setDisabled(true);
            $defaults['authorization'] = 'No authorization - global access';
        }

        $form->addCheckbox('send_session_id', 'Send session id cookie');

        $form->addTextArea('custom_headers', 'Custom headers')
            ->setOption('description', Html::el()->setHtml('Each header on new line. For example: <code>User-agent: Mozilla/5.0</code>'));

        $form->addText('timeout', 'Timeout')
            ->setDefaultValue(30);

        $params = $this->handler->params();
        foreach ($params as $param) {
            $param->updateConsoleForm($form);
        }

        $form->addSubmit('send', 'Try api')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-cloud-upload"></i> Try api');

        $form->setDefaults($defaults);

        $form->onSuccess[] = array($this, 'formSucceeded');
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

        $consoleRequest = new ConsoleRequest($this->handler);
        $result = $consoleRequest->makeRequest($url, $method, (array) $values, $additionalValues, $token);

        /** @var Template $template */
        $template = $this->getTemplate();
        $template->add('response', $result);

        if ($this->getPresenter()->isAjax()) {
            $this->getPresenter()->redrawControl();
        }
    }
}
