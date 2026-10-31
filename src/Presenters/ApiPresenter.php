<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Presenters;

use Nette\Application\IPresenter;
use Nette\Application\IResponse;
use Nette\Application\Request;
use Nette\DI\Attributes\Inject;
use Nette\DI\Container;
use Nette\Http\IRequest;
use Nette\Http\Response;
use Throwable;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\AuthorizationIdentityInterface;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Logger\LogMaskMode;
use Tomaj\NetteApi\Logger\LogMasking;
use Tomaj\NetteApi\Logger\LogValueMasker;
use Tomaj\NetteApi\Logger\LogValueMaskerInterface;
use Tomaj\NetteApi\Misc\CorsOriginAllowlist;
use Tomaj\NetteApi\Misc\CorsOriginCallback;
use Tomaj\NetteApi\Misc\CorsOriginPolicy;
use Tomaj\NetteApi\Misc\CorsOriginValidatorInterface;
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\RateLimit\NoRateLimit;
use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\RateLimit\RateLimitResult;
use Tomaj\NetteApi\RateLimit\RateLimitStage;

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
     * Headers masked in the API log by default and by setMaskedLogHeaders() without arguments
     */
    public const array DEFAULT_MASKED_LOG_HEADERS = LogMasking::DEFAULT_HEADERS;

    /**
     * Header name patterns masked in the API log by default and by setMaskedLogHeaderPatterns() without arguments
     */
    public const array DEFAULT_MASKED_LOG_HEADER_PATTERNS = LogMasking::DEFAULT_HEADER_PATTERNS;

    /**
     * Query parameters masked in the logged URI by default and by setMaskedLogQueryParams() without arguments
     */
    public const array DEFAULT_MASKED_LOG_QUERY_PARAMS = LogMasking::DEFAULT_QUERY_PARAMS;

    public const string MASKED_LOG_HEADER_VALUE = LogValueMasker::MASK;

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

    private ?LogMasking $logMasking = null;

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
     * Replace the names of request headers masked before they are passed to ApiLoggerInterface.
     *
     * Masking is on by default with DEFAULT_MASKED_LOG_HEADERS and DEFAULT_MASKED_LOG_HEADER_PATTERNS; the header names of
     * registered HeaderApiKeyAuthentication are always added. Names are case-insensitive. The patterns stay; an empty list
     * turns header masking off, including the patterns and the authorization names.
     *
     * @param string[] $headers
     */
    public function setMaskedLogHeaders(array $headers = self::DEFAULT_MASKED_LOG_HEADERS): void
    {
        $this->logMasking()->setHeaders($headers, __FUNCTION__);
    }

    public function addMaskedLogHeader(string $header): void
    {
        $this->logMasking()->addHeader($header, __FUNCTION__);
    }

    /**
     * @throws \InvalidArgumentException when the header is not in the list
     */
    public function removeMaskedLogHeader(string $header): void
    {
        $this->logMasking()->removeHeader($header, __FUNCTION__);
    }

    /**
     * @return list<string>
     */
    public function getMaskedLogHeaders(): array
    {
        return $this->logMasking()->getHeaders();
    }

    /**
     * Replace the header name patterns masked in the API log, * stands for any characters: *token*, x-auth-*.
     * An empty list removes only the patterns.
     *
     * @param string[] $patterns
     */
    public function setMaskedLogHeaderPatterns(array $patterns = self::DEFAULT_MASKED_LOG_HEADER_PATTERNS): void
    {
        $this->logMasking()->setHeaderPatterns($patterns, __FUNCTION__);
    }

    public function addMaskedLogHeaderPattern(string $pattern): void
    {
        $this->logMasking()->addHeaderPattern($pattern, __FUNCTION__);
    }

    /**
     * @throws \InvalidArgumentException when the pattern is not in the list
     */
    public function removeMaskedLogHeaderPattern(string $pattern): void
    {
        $this->logMasking()->removeHeaderPattern($pattern, __FUNCTION__);
    }

    /**
     * @return list<string>
     */
    public function getMaskedLogHeaderPatterns(): array
    {
        return $this->logMasking()->getHeaderPatterns();
    }

    /**
     * Replace the names of query parameters whose values are masked in the logged URI.
     *
     * Masking is on by default with DEFAULT_MASKED_LOG_QUERY_PARAMS; the parameter names of registered
     * QueryApiKeyAuthentication are always added. Names are case-insensitive and also match name[] and name[key].
     * An empty list turns query masking off, including the authorization names.
     *
     * @param string[] $params
     */
    public function setMaskedLogQueryParams(array $params = self::DEFAULT_MASKED_LOG_QUERY_PARAMS): void
    {
        $this->logMasking()->setQueryParams($params, __FUNCTION__);
    }

    public function addMaskedLogQueryParam(string $param): void
    {
        $this->logMasking()->addQueryParam($param, __FUNCTION__);
    }

    /**
     * @throws \InvalidArgumentException when the parameter is not in the list
     */
    public function removeMaskedLogQueryParam(string $param): void
    {
        $this->logMasking()->removeQueryParam($param, __FUNCTION__);
    }

    /**
     * @return list<string>
     */
    public function getMaskedLogQueryParams(): array
    {
        return $this->logMasking()->getQueryParams();
    }

    /**
     * How masked values are written: LogMaskMode::Full (***, the default), Partial, Hash or Omit, or your own masker
     */
    public function setLogMasking(LogMaskMode|LogValueMaskerInterface $masking): void
    {
        $this->logMasking()->setMasker($masking instanceof LogMaskMode ? new LogValueMasker($masking) : $masking);
    }

    public function getLogMasking(): LogValueMaskerInterface
    {
        return $this->logMasking()->getMasker();
    }

    private function logMasking(): LogMasking
    {
        return $this->logMasking ??= new LogMasking();
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
        $ipDetector = $rateLimit instanceof NoRateLimit ? null : $this->context->getByType(IpDetectorInterface::class, false);
        $ip = $ipDetector instanceof IpDetector ? $this->remoteAddress() : $ipDetector?->getRequestIp();
        // only IpKey needs the detector (it throws without one), a custom limiter without it keeps working
        $hasIpDetector = $ipDetector !== null;

        $rateLimitResponse = $this->checkRateLimit($rateLimit, new RateLimitContext($api, $request, RateLimitStage::BeforeAuthorization, $ip, hasIpDetector: $hasIpDetector));
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        $paramsProcessor = new ParamsProcessor($handler->params());
        $params = $paramsProcessor->isError() ? [] : $paramsProcessor->getValues();

        $authResponse = $this->checkAuth($authorization, $params);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $identity = $authorization instanceof AuthorizationIdentityInterface ? $authorization->getIdentity() : null;
        $rateLimitResponse = $this->checkRateLimit($rateLimit, new RateLimitContext($api, $request, RateLimitStage::AfterAuthorization, $ip, $identity, $hasIpDetector));
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
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

    /**
     * The IP for the rate limits instead of the default IpDetector, which trusts headers that any client can send,
     * so every value would get a new quota. Nette trusts the forwarded headers only from the proxies in http.proxy.
     */
    private function remoteAddress(): string
    {
        $address = $this->context->getByType(IRequest::class, false)?->getRemoteAddress() ?? ($_SERVER['REMOTE_ADDR'] ?? null);

        return is_string($address) ? $address : 'cli';
    }

    /**
     * Sends RateLimit-Policy and RateLimit (draft-ietf-httpapi-ratelimit-headers) for every policy that applied,
     * and answers 429 with Retry-After when any of them did not accept the request
     */
    private function checkRateLimit(RateLimiterInterface $rateLimit, RateLimitContext $context): ?IResponse
    {
        try {
            $results = $rateLimit->consume($context);
        } catch (Throwable $throwable) {
            // a wrong configuration or a bug of an own limiter, answered like an exception of a handler
            $response = $this->errorHandler->handle($throwable, []);
            $this->response->setCode($response->getCode());
            return $response;
        }

        $rejected = null;
        foreach ($results as $result) {
            // a structured field string: an own limiter may name its policy with any characters
            $policy = '"' . addcslashes((string) preg_replace('~[^\x20-\x7E]~', '', $result->policy), '"\\') . '"';
            // the numbers of the headers are non-negative, an own limiter may return less than 0
            $this->response->addHeader('RateLimit-Policy', $policy . ';q=' . max(0, $result->limit) . ($result->window > 0 ? ';w=' . $result->window : ''));
            $this->response->addHeader('RateLimit', $policy . ';r=' . max(0, $result->remaining) . ($result->resetAfter === null ? '' : ';t=' . max(0, $result->resetAfter)));
            if (!$result->accepted && ($rejected === null || $result->retryAfter > $rejected->retryAfter)) {
                $rejected = $result;
            }
        }

        if (!$rejected instanceof RateLimitResult) {
            return null;
        }

        $this->response->setCode(Response::S429_TOO_MANY_REQUESTS);
        $retryAfter = $rejected->retryAfter === null ? null : max(0, $rejected->retryAfter);
        if ($retryAfter !== null) {
            $this->response->addHeader('Retry-After', (string) $retryAfter);
        }

        return $rejected->errorResponse
            ?? $this->errorHandler->handleRateLimit(new RateLimitResponse(max(0, $rejected->limit), max(0, $rejected->remaining), $retryAfter));
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

        $authHeaders = $authCookies = $authParams = [];
        foreach ($this->apiDecider->getAuthorizations() as $authorization) {
            if ($authorization instanceof HeaderApiKeyAuthentication) {
                $authHeaders[] = $authorization->getHeaderName();
            } elseif ($authorization instanceof CookieApiKeyAuthentication) {
                $authCookies[] = $authorization->getCookieName();
            } elseif ($authorization instanceof QueryApiKeyAuthentication) {
                $authParams[] = $authorization->getQueryParamName();
            }
        }
        $masking = $this->logMasking();

        $ipDetector = $this->context->getByType(IpDetectorInterface::class);
        $logger->log(
            $code,
            $request->getMethod() ?? '',
            $masking->headers($headers, $authHeaders, $authCookies),
            $masking->uri(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '', $authParams),
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
