<?php

namespace Tomaj\NetteApi\Presenters;

use Exception;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\DI\Container;
use Nette\Http\Response;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tracy\Debugger;

/**
 * @property-read Container $context
 */
class ApiPresenter extends Presenter
{
    /** @var ApiDecider @inject */
    public $apiDecider;

    /**
     * CORS header settings
     *
     * Available values:
     *   'auto'  - send back header Access-Control-Allow-Origin with domain that made request
     *   '*'     - send header with '*' - this will workf fine if you dont need to send cookies via ajax calls to api
     *             with jquery $.ajax with xhrFields: { withCredentials: true } settings
     *   'off'   - will not send any CORS header
     *   other   - any other value will be send in Access-Control-Allow-Origin header
     *
     * @var string
     */
    protected $corsHeader = '*';

    public function startup(): void
    {
        parent::startup();
        $this->autoCanonicalize = false;
    }

    /**
     * Set cors header
     *
     * See description to property $corsHeader for valid inputs
     *
     * @param string $corsHeader
     */
    public function setCorsHeader(string $corsHeader): void
    {
        $this->corsHeader = $corsHeader;
    }

    public function renderDefault(): void
    {
        $start = microtime(true);

        $this->sendCorsHeaders();

        $api = $this->getApi();
        $handler = $api->getHandler();
        $authorization = $api->getAuthorization();

        if ($this->checkAuth($authorization) === false) {
            return;
        }

        $params = $this->processInputParams($handler);
        if ($params === null) {
            return;
        }

        try {
            $response = $handler->handle($params);
            $code = $response->getCode();
        } catch (Exception $exception) {
            if (Debugger::isEnabled()) {
                $response = new JsonApiResponse(500, ['status' => 'error', 'message' => 'Internal server error', 'detail' => $exception->getMessage()]);
            } else {
                $response = new JsonApiResponse(500, ['status' => 'error', 'message' => 'Internal server error']);
            }
            $code = $response->getCode();
            Debugger::log($exception, Debugger::EXCEPTION);
        }

        $end = microtime(true);

        if ($this->context->findByType(ApiLoggerInterface::class)) {
            /** @var ApiLoggerInterface $apiLogger */
            $apiLogger = $this->context->getByType(ApiLoggerInterface::class);
            $this->logRequest($apiLogger, $code, $end - $start);
        }

        // output to nette
        $this->getHttpResponse()->setCode($code);
        $this->sendResponse($response);
    }

    private function getApi(): Api
    {
        return $this->apiDecider->getApi(
            $this->getRequest()->getMethod(),
            $this->params['version'],
            $this->params['package'],
            $this->params['apiAction']
        );
    }

    private function checkAuth(ApiAuthorizationInterface $authorization): bool
    {
        if (!$authorization->authorized()) {
            $this->getHttpResponse()->setCode(Response::S403_FORBIDDEN);
            $this->sendResponse(new JsonResponse(['status' => 'error', 'message' => $authorization->getErrorMessage()]));
            return false;
        }
        return true;
    }

    private function processInputParams(ApiHandlerInterface $handler): ?array
    {
        $paramsProcessor = new ParamsProcessor($handler->params());
        if ($paramsProcessor->isError()) {
            $this->getHttpResponse()->setCode(Response::S400_BAD_REQUEST);
            if (Debugger::isEnabled()) {
                $response = new JsonResponse(['status' => 'error', 'message' => 'wrong input', 'detail' => $paramsProcessor->getErrors()]);
            } else {
                $response = new JsonResponse(['status' => 'error', 'message' => 'wrong input']);
            }
            $this->sendResponse($response);
            return null;
        }
        return $paramsProcessor->getValues();
    }

    private function logRequest(ApiLoggerInterface $logger, int $code, float $elapsed): void
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) === 'HTTP_') {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$key] = $value;
                }
            }
        }

        $requestHeaders = '';
        foreach ($headers as $key => $value) {
            $requestHeaders .= "$key: $value\n";
        }

        $ipDetector = $this->context->getByType(IpDetectorInterface::class);
        $logger->log(
            $code,
            $this->getRequest()->getMethod(),
            $requestHeaders,
            filter_input(INPUT_SERVER, 'REQUEST_URI'),
            $ipDetector->getRequestIp(),
            filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'),
            (int) ($elapsed) * 1000
        );
    }

    protected function sendCorsHeaders(): void
    {
        $this->getHttpResponse()->addHeader('Access-Control-Allow-Methods', 'POST, DELETE, PUT, GET, OPTIONS');

        if ($this->corsHeader === 'auto') {
            $domain = $this->getRequestDomain();
            if ($domain !== null) {
                $this->getHttpResponse()->addHeader('Access-Control-Allow-Origin', $domain);
                $this->getHttpResponse()->addHeader('Access-Control-Allow-Credentials', 'true');
            }
            return;
        }

        if ($this->corsHeader === '*') {
            $this->getHttpResponse()->addHeader('Access-Control-Allow-Origin', '*');
            return;
        }

        if ($this->corsHeader !== 'off') {
            $this->getHttpResponse()->addHeader('Access-Control-Allow-Origin', $this->corsHeader);
        }
    }

    private function getRequestDomain(): ?string
    {
        if (!filter_input(INPUT_SERVER, 'HTTP_REFERER')) {
            return null;
        }
        $refererParsedUrl = parse_url(filter_input(INPUT_SERVER, 'HTTP_REFERER'));
        if (!(isset($refererParsedUrl['scheme']) && isset($refererParsedUrl['host']))) {
            return null;
        }
        $url = $refererParsedUrl['scheme'] . '://' . $refererParsedUrl['host'];
        if (isset($refererParsedUrl['port']) && $refererParsedUrl['port'] !== 80) {
            $url .= ':' . $refererParsedUrl['port'];
        }
        return $url;
    }
}
