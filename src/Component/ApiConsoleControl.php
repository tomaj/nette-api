<?php

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Http\Request;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Params\InputParam;
use Tracy\Debugger;

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

//        $form->setRenderer(new BootstrapRenderer());

        $uri = $this->request->getUrl();
        $scheme = $uri->scheme;
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }
        $url = $scheme . '://' . $uri->host . '/api/' . $this->endpoint->getUrl();

        $form->addText('api_url', 'Api Url');

        $defaults['api_url'] = $url;

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
                $c = $form->addText($key, $param->getKey());
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

    public function formSucceeded($form, $values)
    {
        $url = $values['api_url'];

        $token = false;
        if (isset($values['token'])) {
            $token = $values['token'];
            unset($values['token']);
        }

        $postFields = [];
        $getFields = [];

        if (isset($values['token_csfr'])) {
            $postFields[] = "token=" . urlencode($values['token_csfr']);
            unset($values['token_csfr']);
        }

        $params = $this->handler->params();

        foreach ($values as $key => $value) {
            if (strstr($key, '___') !== false) {
                $parts = explode('___', $key);
                $key = $parts[0];
            }
            foreach ($params as $param) {
                if ($param->getKey() == $key) {
                    if (!$value) {
                        continue;
                    }
                    if ($param->isMulti()) {
                        $valueKey = '';
                        if (strstr($value, '=') !== false) {
                            $parts = explode('=', $value);
                            $valueKey = $parts[0];
                            $value = $parts[1];
                        }
                        $valueData = $key . "[$valueKey]=$value";
                    } else {
                        $valueData = "$key=$value";
                    }

                    if ($param->getType() == InputParam::TYPE_POST) {
                        $postFields[] = $valueData;
                    } else {
                        $getFields[] = $valueData;
                    }
                }
            }
        }

        if (count($getFields)) {
            $url = $url . '?' . implode('&', $getFields);
        }


        Debugger::timer();

        $result = 'Requesting url: ' . $url . "\n";
        $result .= "POST Params:\n\t";
        $result .= implode("\n\t", $postFields);
        $result .= "\n";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_NOBODY, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        if (count($postFields)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, implode('&', $postFields));
        }

        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        if ($token) {
            $headers = array('Authorization: Bearer ' . $token);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            $result .= implode("\n", $headers);
        }


        $result .= "\n\n--------------------------------------------------------\n\n";

        $responseBody = curl_exec($curl);

        $result .= "\n";

        $elapsed = intval(Debugger::timer() * 1000);
        $result .= "Took: {$elapsed}ms\n";

        $curlErrorNumber = curl_errno($curl);
        $curlError = curl_error($curl);
        if ($curlErrorNumber > 0) {
            $result .= "HTTP Error: " . $curlError . "\n";
        } else {
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            $result .= "HTTP code: $httpcode\n\n";

            $body = $responseBody;
            $decoded = json_decode($body);
            if ($decoded) {
                $body = json_encode($decoded, JSON_PRETTY_PRINT);
            }

            $result .= "Result:\n\n{$body}\n\n";
        }

        $this->template->response = $result;
    }
}