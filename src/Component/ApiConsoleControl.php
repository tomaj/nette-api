<?php

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Http\Request;
use Tomaj\Form\Renderer\BootstrapRenderer;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Misc\ConsoleRequest;
use Tomaj\NetteApi\Params\InputParam;

class ApiConsoleControl extends Control
{
    private $endpoint;

    private $handler;

    private $authorization;

    private $request;

    public function __construct(Request $request, EndpointIdentifier $endpoint, ApiHandlerInterface $handler, ApiAuthorizationInterface $authorization)
    {
        parent::__construct(null, 'apiconsolecontrol');
        $this->endpoint = $endpoint;
        $this->handler = $handler;
        $this->authorization = $authorization;
        $this->request = $request;
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . '/console.latte');
        $this->template->render();
    }

    protected function createComponentConsoleForm()
    {
        $form = new Form();

        $defaults = [];

        $form->setRenderer(new BootstrapRenderer());

        $uri = $this->request->getUrl();
        $scheme = $uri->scheme;
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }
        $url = $scheme . '://' . $uri->host . '/api/' . $this->endpoint->getUrl();

        $form->addText('api_url', 'Api Url');
        $defaults['api_url'] = $url;

        $form->addText('method', 'Method');
        $defaults['method'] = $this->endpoint->getMethod();

        if ($this->authorization instanceof BearerTokenAuthorization) {
            $form->addText('token', 'Token:')
                ->setAttribute('placeholder', 'napište token');
        } elseif ($this->authorization instanceof NoAuthorization) {
            $form->addText('authorization', 'Authorization')
                ->setDisabled(true);
            $defaults['authorization'] = 'No authorization - global access';
        }

        $params = $this->handler->params();
        foreach ($params as $param) {
            $count = $param->isMulti() ? 5 : 1;
            for ($i = 0; $i < $count; $i++) {
                $key = $param->getKey();
                if ($param->isMulti()) {
                    $key = $key . '___' . $i;
                }
                $c = $form->addText($key, $this->getParamLabel($param));
                if ($param->getAvailableValues()) {
                    $c->setOption('description', 'available values: ' . implode(' | ', $param->getAvailableValues()));
                }
            }
        }

        $form->addSubmit('send', 'Otestuj')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-cloud-upload"></i> Try api');

        $form->setDefaults($defaults);

        $form->onSuccess[] = array($this, 'formSucceeded');
        return $form;
    }

    private function getParamLabel(InputParam $param)
    {
        $title = $param->getKey();
        if ($param->isRequired()) {
            $title .= ' *';
        }
        return $title;
    }

    public function formSucceeded($form, $values)
    {
        $url = $values['api_url'];

        $token = false;
        if (isset($values['token'])) {
            $token = $values['token'];
            unset($values['token']);
        }

        $method = $values['method'];
        unset($values['method']);

        $consoleRequest = new ConsoleRequest($this->handler);
        $result = $consoleRequest->makeRequest($url, $method, $values, $token);

        $this->template->add('response', $result);
    }
}
