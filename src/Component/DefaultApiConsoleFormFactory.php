<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Form;
use Nette\Http\IRequest;
use Nette\Utils\Html;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\BasicAuthentication;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Link\ApiLink;

class DefaultApiConsoleFormFactory implements ApiConsoleFormFactoryInterface
{
    private const HTTP_PORT = 80;

    public function create(
        IRequest $request,
        EndpointInterface $endpoint,
        ApiHandlerInterface $handler,
        ApiAuthorizationInterface $authorization,
        ?ApiLink $apiLink = null
    ): Form {
        $form = $this->createForm();

        $defaults = [];

        $form->addText('api_url', 'Api Url');
        $defaults['api_url'] = $this->getUrl($request, $endpoint, $apiLink);

        $form->addText('api_method', 'Method');
        $defaults['api_method'] = $endpoint->getMethod();

        $this->addAuthorization($form, $authorization, $defaults);

        $this->addCommonFields($form);

        $this->addParams($form, $handler);

        $this->addSubmit($form);

        $form->setDefaults($defaults);

        return $form;
    }

    protected function createForm(): Form
    {
        return new Form();
    }

    protected function getUrl(IRequest $request, EndpointInterface $endpoint, ?ApiLink $apiLink = null): string
    {
        if ($apiLink) {
            return $apiLink->link($endpoint);
        }

        $uri = $request->getUrl();
        $scheme = $uri->scheme;
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }
        $port = '';
        if ($uri->scheme === 'http' && $uri->port !== self::HTTP_PORT) {
            $port = ':' . $uri->port;
        }

        return $scheme . '://' . $uri->host . $port . '/api/' . $endpoint->getUrl();
    }

    /**
     * @param array<string, mixed> $defaults
     */
    protected function addAuthorization(Form $form, ApiAuthorizationInterface $authorization, array &$defaults): void
    {
        if ($authorization instanceof BearerTokenAuthorization) {
            $form->addText('token', 'Token')
                ->setHtmlAttribute('placeholder', 'Enter token');
        } elseif ($authorization instanceof BasicAuthentication) {
            $form->addText('basic_authentication_username', 'Username')
                ->setHtmlAttribute('placeholder', 'Enter basic authentication username');
            $form->addText('basic_authentication_password', 'Password')
                ->setHtmlAttribute('placeholder', 'Enter basic authentication password');
        } elseif ($authorization instanceof QueryApiKeyAuthentication) {
            $form->addText($authorization->getQueryParamName(), 'API key')
                ->setHtmlAttribute('placeholder', 'Enter API key');
        } elseif ($authorization instanceof HeaderApiKeyAuthentication) {
            $form->addText('header_api_key', 'API key')
                ->setHtmlAttribute('placeholder', 'Enter API key');
        } elseif ($authorization instanceof CookieApiKeyAuthentication) {
            $form->addText('cookie_api_key', 'API key')
                ->setHtmlAttribute('placeholder', 'Enter API key');
        } elseif ($authorization instanceof NoAuthorization) {
            $form->addText('authorization', 'Authorization')
                ->setDisabled(true);
            $defaults['authorization'] = 'No authorization - global access';
        }
    }

    protected function addCommonFields(Form $form): void
    {
        $form->addCheckbox('send_session_id', 'Send session id cookie');

        $form->addTextArea('custom_headers', 'Custom headers')
            ->setOption('description', Html::el()->setHtml('Each header on new line. For example: <code>User-agent: Mozilla/5.0</code>'));

        $form->addText('timeout', 'Timeout')
            ->setDefaultValue(30);
    }

    protected function addParams(Form $form, ApiHandlerInterface $handler): void
    {
        $params = $handler->params();
        foreach ($params as $param) {
            $param->updateConsoleForm($form);
        }
    }

    protected function addSubmit(Form $form): void
    {
        $form->addSubmit('send', 'Try api')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-cloud-upload"></i> Try api');
    }
}
