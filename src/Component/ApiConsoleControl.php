<?php

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Http\IRequest;
use Nette\Utils\ArrayHash;
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
        $this->getTemplate()->setFile(__DIR__ . '/console.latte');
        $this->getTemplate()->add('handler', $this->handler);
        $this->getTemplate()->render();
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

        $token = false;
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

        $consoleRequest = new ConsoleRequest($this->handler);
        $result = $consoleRequest->makeRequest($url, $method, (array) $values, $additionalValues, $token);

        $this->getTemplate()->add('response', $result);

        if ($this->getPresenter()->isAjax()) {
            $this->getPresenter()->redrawControl();
        }
    }
}
