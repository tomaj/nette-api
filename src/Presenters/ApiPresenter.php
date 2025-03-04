<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Presenters;

use Nette\Application\IPresenter;
use Nette\Application\IResponse;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Nette\DI\Container;
use Nette\Http\Response;
use Throwable;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\Output\OutputInterface;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;

final class ApiPresenter implements IPresenter
{
    /** @var ApiDecider @inject */
    public $apiDecider;

    /** @var Response @inject */
    public $response;

    /** @var Container @inject */
    public $context;

    /** @var ConfiguratorInterface @inject */
    public $outputConfigurator;

    /** @var ErrorHandlerInterface @inject */
    public $errorHandler;

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

    public function run(Request $request): IResponse
    {
        $start = microtime(true);

        $this->sendCorsHeaders();

        $api = $this->getApi($request);
        $handler = $api->getHandler();

        $authorization = $api->getAuthorization();
        $rateLimit = $api->getRateLimit();

        $rateLimitResponse = $this->checkRateLimit($rateLimit);
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        $paramsProcessor = new ParamsProcessor($handler->params());
        if ($paramsProcessor->isError()) {
            $response = $this->errorHandler->handleInputParams($paramsProcessor->getErrors());
            $this->response->setCode($response->getCode());
            return $response;
        }
        $params = $paramsProcessor->getValues();

        $authResponse = $this->checkAuth($authorization, $params);
        if ($authResponse !== null) {
            return $authResponse;
        }

        try {
            $response = $handler->handle($params);
            $code = $response->getCode();

            if ($this->outputConfigurator->validateSchema()) {
                $outputs = $handler->outputs();
                $outputValid = count($outputs) === 0; // back compatibility for handlers with no outputs defined
                $outputValidatorErrors = [];
                foreach ($outputs as $output) {
                    if (!$output instanceof OutputInterface) {
                        $outputValidatorErrors[] = ["Output does not implement OutputInterface"];
                        continue;
                    }
                    $validationResult = $output->validate($response);
                    if ($validationResult->isOk()) {
                        $outputValid = true;
                        break;
                    }
                    $outputValidatorErrors[] = $validationResult->getErrors();
                }
                if (!$outputValid) {
                    $response = $this->errorHandler->handleSchema($outputValidatorErrors, $params);
                    $code = $response->getCode();
                }
            }
        } catch (Throwable $exception) {
            $response = $this->errorHandler->handle($exception, $params);
            $code = $response->getCode();
        }

        $end = microtime(true);

        if ($this->context->findByType(ApiLoggerInterface::class)) {
            /** @var ApiLoggerInterface $apiLogger */
            $apiLogger = $this->context->getByType(ApiLoggerInterface::class);
            $this->logRequest($request, $apiLogger, $code, $end - $start);
        }

        // output to nette
        $this->response->setCode($code);
        return $response;
    }

    private function getApi(Request $request): Api
    {
        return $this->apiDecider->getApi(
            $request->getMethod(),
            $request->getParameter('version'),
            $request->getParameter('package'),
            $request->getParameter('apiAction')
        );
    }

    private function checkAuth(ApiAuthorizationInterface $authorization, array $params): ?IResponse
    {
        try {
            if (!$authorization->authorized()) {
                $response = $this->errorHandler->handleAuthorization($authorization, $params);
                $this->response->setCode($response->getCode());
                return $response;
            }
        } catch (Throwable $exception) {
            $response = $this->errorHandler->handleAuthorizationException($exception, $params);
            $this->response->setCode($response->getCode());
            return $response;
        }
        return null;
    }

    private function checkRateLimit(RateLimitInterface $rateLimit): ?IResponse
    {
        $rateLimitResponse = $rateLimit->check();
        if (!$rateLimitResponse) {
            return null;
        }

        $limit = $rateLimitResponse->getLimit();
        $remaining = $rateLimitResponse->getRemaining();
        $retryAfter = $rateLimitResponse->getRetryAfter();

        $this->response->addHeader('X-RateLimit-Limit', (string)$limit);
        $this->response->addHeader('X-RateLimit-Remaining', (string)$remaining);

        if ($remaining === 0) {
            $this->response->setCode(Response::S429_TOO_MANY_REQUESTS);
            $this->response->addHeader('Retry-After', (string)$retryAfter);
            return $rateLimitResponse->getErrorResponse() ?: new JsonResponse(['status' => 'error', 'message' => 'Too many requests. Retry after ' . $retryAfter . ' seconds.']);
        }
        return null;
    }

    private function logRequest(Request $request, ApiLoggerInterface $logger, int $code, float $elapsed): void
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
            $request->getMethod(),
            $requestHeaders,
            (string) filter_input(INPUT_SERVER, 'REQUEST_URI'),
            $ipDetector ? $ipDetector->getRequestIp() : '',
            (string) filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'),
            (int) ($elapsed) * 1000
        );
    }

    protected function sendCorsHeaders(): void
    {
        $this->response->addHeader('Access-Control-Allow-Methods', 'POST, DELETE, PUT, GET, OPTIONS');

        if ($this->corsHeader === 'auto') {
            $domain = $this->getRequestDomain();
            if ($domain !== null) {
                $this->response->addHeader('Access-Control-Allow-Origin', $domain);
                $this->response->addHeader('Access-Control-Allow-Credentials', 'true');
            }
            return;
        }

        if ($this->corsHeader === '*') {
            $this->response->addHeader('Access-Control-Allow-Origin', '*');
            return;
        }

        if ($this->corsHeader !== 'off') {
            $this->response->addHeader('Access-Control-Allow-Origin', $this->corsHeader);
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
