<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Presenters;

use Nette\Application\IPresenter;
use Nette\Application\IResponse;
use Nette\Application\Request;
use Nette\DI\Attributes\Inject;
use Nette\DI\Container;
use Nette\Http\Response;
use Throwable;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Misc\CorsOriginAllowlist;
use Tomaj\NetteApi\Misc\CorsOriginCallback;
use Tomaj\NetteApi\Misc\CorsOriginPolicy;
use Tomaj\NetteApi\Misc\CorsOriginValidatorInterface;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;

final class ApiPresenter implements IPresenter
{
    private const int TO_SECONDS = 1000;

    private const int HTTP_PORT = 80;

    /**
     * Start of the error for the renamed 'auto' CORS mode, shared with NetteApiExtension
     *
     * @internal
     */
    public const string AUTO_RENAMED = "The CORS mode 'auto' was renamed to 'reflect-unsafe' in 4.0. It is unsafe: it sends the origin of the Referer header back in Access-Control-Allow-Origin with Access-Control-Allow-Credentials: true, so any website can make requests with the user's cookies and read the responses. ";

    /**
     * Headers masked in the API log by setMaskedLogHeaders() without arguments
     */
    public const array DEFAULT_MASKED_LOG_HEADERS = ['Authorization', 'Proxy-Authorization', 'Cookie', 'Set-Cookie', 'X-Api-Key'];

    public const string MASKED_LOG_HEADER_VALUE = '***';

    #[Inject]
    public ApiDecider $apiDecider;

    #[Inject]
    public Response $response;

    #[Inject]
    public Container $context;

    #[Inject]
    public ConfiguratorInterface $outputConfigurator;

    #[Inject]
    public ErrorHandlerInterface $errorHandler;

    /**
     * CORS header settings
     *
     * Available values:
     *   'off'   - no CORS header (the default), use setCorsAllowedOrigins() or setCorsOriginValidator() to allow browsers on other origins
     *   'reflect-unsafe' - UNSAFE: sends back the origin of the Referer header with
     *             Access-Control-Allow-Credentials: true and Vary: Origin, Referer, so ANY website can read responses with the user's cookies
     *             (the 'auto' mode of 3.x), use setCorsAllowedOrigins() instead
     *   '*'     - Access-Control-Allow-Origin: *, for public APIs called without cookies
     *   other   - one origin sent as Access-Control-Allow-Origin, for example 'https://app.example.com'
     *
     * @var string
     */
    protected $corsHeader = 'off';

    /** setCorsAllowedOrigins() or setCorsOriginValidator() */
    private ?CorsOriginPolicy $corsAllowlist = null;

    /** @var string[] lowercase names of headers whose values are masked in the API log */
    private array $maskedLogHeaders = [];

    /**
     * Set cors header
     *
     * See description to property $corsHeader for valid inputs
     */
    public function setCorsHeader(CorsMode|string $corsHeader): void
    {
        $corsHeader = $corsHeader instanceof CorsMode ? $corsHeader->value : $corsHeader;
        if (strtolower($corsHeader) === 'auto') {
            throw new \InvalidArgumentException(self::AUTO_RENAMED . "Safe replacement: setCorsAllowedOrigins(['https://app.example.com'], true) listing the origins that call the API; pass true only when the browser sends cookies or uses withCredentials. To keep the old behaviour anyway, use setCorsHeader(CorsMode::ReflectUnsafe) or setCorsHeader('reflect-unsafe'). See UPGRADE-4.0.md.");
        }
        if (CorsMode::tryFrom($corsHeader) === null && !CorsOriginAllowlist::isOrigin($corsHeader)) {
            throw new \InvalidArgumentException(sprintf(
                "setCorsHeader('%s'): the value is neither a CORS mode nor an origin, it would be sent as Access-Control-Allow-Origin and no browser would accept it. Use 'off', '*', 'reflect-unsafe' (or a CorsMode case), or one origin in the form scheme://host[:port] without a path or trailing slash, for example setCorsHeader('https://app.example.com'); for more origins use setCorsAllowedOrigins().",
                $corsHeader,
            ));
        }
        $this->corsHeader = $corsHeader;
    }

    /**
     * Allow CORS only for the listed origins (compared with the request Origin header).
     *
     * When set, it takes precedence over setCorsHeader().
     *
     * @param string[] $origins e.g. ['https://app.example.com', 'http://localhost:3000']
     * @param bool $allowCredentials send Access-Control-Allow-Credentials: true for allowed origins
     */
    public function setCorsAllowedOrigins(array $origins, bool $allowCredentials = false): void
    {
        $this->checkCorsPolicyConflict(CorsOriginAllowlist::class);
        $this->corsAllowlist = new CorsOriginAllowlist($origins, $allowCredentials);
    }

    /**
     * Allow CORS for origins accepted by a validator called on each request with the request Origin header,
     * for origins not known in advance (for example customer domains stored in a database).
     *
     * The validator gets only an origin in the form scheme://host[:port] and must return a bool; an allowed origin
     * is reflected in Access-Control-Allow-Origin with Vary: Origin. When set, it takes precedence over setCorsHeader().
     *
     * @param CorsOriginValidatorInterface|callable(string): bool $validator
     * @param bool $allowCredentials send Access-Control-Allow-Credentials: true for allowed origins
     */
    public function setCorsOriginValidator(mixed $validator, bool $allowCredentials = false): void
    {
        $this->checkCorsPolicyConflict(CorsOriginCallback::class);
        $this->corsAllowlist = new CorsOriginCallback($validator, $allowCredentials);
    }

    /**
     * @param class-string<CorsOriginPolicy> $policy
     */
    private function checkCorsPolicyConflict(string $policy): void
    {
        if ($this->corsAllowlist !== null && !$this->corsAllowlist instanceof $policy) {
            throw new \LogicException('setCorsAllowedOrigins() and setCorsOriginValidator() are both called, but only one of them can decide which origins are allowed. Keep one: list fixed origins with setCorsAllowedOrigins(), or check them in the validator, for example fn (string $origin): bool => in_array($origin, $fixedOrigins, true) || $this->isCustomerDomain($origin).');
        }
    }

    /**
     * Mask values of sensitive request headers before they are passed to ApiLoggerInterface.
     *
     * Header names are case-insensitive. Masking is off by default in 3.x.
     *
     * @param string[] $headers
     */
    public function setMaskedLogHeaders(array $headers = self::DEFAULT_MASKED_LOG_HEADERS): void
    {
        $this->maskedLogHeaders = array_map(strtolower(...), $headers);
    }

    #[\Override]
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
        $params = $paramsProcessor->isError() ? [] : $paramsProcessor->getValues();

        $authResponse = $this->checkAuth($authorization, $params);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if ($paramsProcessor->isError()) {
            $response = $this->errorHandler->handleInputParams($paramsProcessor->getErrors());
            $this->response->setCode($response->getCode());
            return $response;
        }

        try {
            $response = $handler instanceof DefaultHandler
                ? $this->errorHandler->handleNotFound($api->getEndpoint())
                : $handler->handle($params);
            $code = $response->getCode();

            if ($this->outputConfigurator->validateSchema()) {
                $outputs = $handler->outputs();
                $outputValid = $outputs === []; // back compatibility for handlers with no outputs defined
                $outputValidatorErrors = [];
                foreach ($outputs as $output) {
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
        } catch (Throwable $throwable) {
            $response = $this->errorHandler->handle($throwable, $params);
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
            $request->getMethod() ?? '',
            TypeHelper::toString($request->getParameter('version')),
            TypeHelper::toString($request->getParameter('package')),
            $request->getParameter('apiAction') === null ? null : TypeHelper::toString($request->getParameter('apiAction')),
        );
    }

    /**
     * @param array<mixed> $params
     */
    private function checkAuth(ApiAuthorizationInterface $authorization, array $params): ?IResponse
    {
        try {
            if (!$authorization->authorized()) {
                $response = $this->errorHandler->handleAuthorization($authorization, $params);
                $this->response->setCode($response->getCode());
                return $response;
            }
        } catch (Throwable $throwable) {
            $response = $this->errorHandler->handleAuthorizationException($throwable, $params);
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
            return $rateLimitResponse->getErrorResponse() ?? $this->errorHandler->handleRateLimit($rateLimitResponse);
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
                if (str_starts_with($name, 'HTTP_')) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$key] = $value;
                }
            }
        }

        $requestHeaders = '';
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $this->maskedLogHeaders, true)) {
                $value = self::MASKED_LOG_HEADER_VALUE;
            }
            $requestHeaders .= sprintf('%s: %s%s', $key, TypeHelper::toString($value), PHP_EOL);
        }

        $ipDetector = $this->context->getByType(IpDetectorInterface::class);
        $logger->log(
            $code,
            $request->getMethod() ?? '',
            $requestHeaders,
            (string)filter_input(INPUT_SERVER, 'REQUEST_URI'),
            $ipDetector->getRequestIp(),
            (string)filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'),
            (int)($elapsed * self::TO_SECONDS),
        );
    }

    protected function sendCorsHeaders(): void
    {
        $this->response->addHeader('Access-Control-Allow-Methods', 'POST, DELETE, PUT, GET, OPTIONS');

        if ($this->corsAllowlist !== null) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
            foreach ($this->corsAllowlist->headers(is_string($origin) ? $origin : null) as $name => $value) {
                $this->response->addHeader($name, $value);
            }

            return;
        }

        if ($this->corsHeader === 'reflect-unsafe') {
            // the reflected value differs per request (it comes from the Referer), so shared caches must not reuse it for another site
            $this->response->addHeader('Vary', 'Origin, Referer');
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
        // read like HTTP_ORIGIN in sendCorsHeaders(), from $_SERVER
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        if (!is_string($referer) || $referer === '') {
            return null;
        }

        $refererParsedUrl = parse_url($referer);
        if (!is_array($refererParsedUrl) || !(isset($refererParsedUrl['scheme']) && isset($refererParsedUrl['host']))) {
            return null;
        }

        $url = $refererParsedUrl['scheme'] . '://' . $refererParsedUrl['host'];
        if (isset($refererParsedUrl['port']) && $refererParsedUrl['port'] !== self::HTTP_PORT) {
            $url .= ':' . $refererParsedUrl['port'];
        }

        return $url;
    }
}
