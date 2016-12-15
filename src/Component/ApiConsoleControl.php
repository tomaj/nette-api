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
        $this->getTemplate()->setFile(__DIR__ . '/console.latte');
        $this->getTemplate()->render();
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
        $port = '';
        if ($uri->scheme == 'http' && $uri->port != 80) {
            $port = ':' . $uri->port;
        }
        $url = $scheme . '://' . $uri->host . $port . '/api/' . $this->endpoint->getUrl();

        $form->addText('api_url', 'Api Url');
        $defaults['api_url'] = $url;

        $form->addText('method', 'Method');
        $defaults['method'] = $this->endpoint->getMethod();

        if ($this->authorization instanceof BearerTokenAuthorization) {
            $form->addText('token', 'Token')
                ->setAttribute('placeholder', 'Enter token');
        } elseif ($this->authorization instanceof NoAuthorization) {
            $form->addText('authorization', 'Authorization')
                ->setDisabled(true);
            $defaults['authorization'] = 'No authorization - global access';
        }

        $form->addCheckbox('send_phpsessid', 'Send PHPSESSID');

        $params = $this->handler->params();
        foreach ($params as $param) {
            $count = $param->isMulti() ? 5 : 1;
            for ($i = 0; $i < $count; $i++) {
                $key = $param->getKey();
                if ($param->isMulti()) {
                    $key = $key . '___' . $i;
                }

                if ($param->getAvailableValues() && is_array($param->getAvailableValues())) {
                    $c = $form->addSelect($key, $this->getParamLabel($param), array_combine($param->getAvailableValues(), $param->getAvailableValues()));
                    if (!$param->isRequired()) {
                        $c->setPrompt('Select ' . $this->getLabel($param));
                    }
                } elseif ($param->getAvailableValues() && is_string($param->getAvailableValues())) {
                    $c = $form->addText($key, $this->getParamLabel($param))->setDisabled(true);
                    $defaults[$key] = $param->getAvailableValues();
                } elseif ($param->getType() == InputParam::TYPE_FILE) {
                    $c = $form->addUpload($key, $this->getParamLabel($param));
                } elseif ($param->getType() == InputParam::TYPE_POST_RAW) {
                    $c = $form->addTextArea('post_raw', $this->getParamLabel($param))
                        ->setAttribute('rows', 10);
                } else {
                    $c = $form->addText($key, $this->getParamLabel($param));
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

    private function getLabel(InputParam $param)
    {
        return ucfirst(str_replace('_', ' ', $param->getKey()));
    }

    private function getParamLabel(InputParam $param)
    {
        $title = $this->getLabel($param);
        if ($param->isRequired()) {
            $title .= ' *';
        }
        $title .= ' (' . $param->getType() . ')';
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

        $additionalValues = [];
        if (isset($values['send_phpsessid']) && $values['send_phpsessid']) {
            $additionalValues['cookieFields']['PHPSESSID'] = session_id();
            session_write_close();
        }

        $consoleRequest = new ConsoleRequest($this->handler);
        $result = $consoleRequest->makeRequest($url, $method, (array) $values, $additionalValues, $token);

        $this->getTemplate()->add('response', $result);
    }
}
