<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Nette\Http\Response;
use Nette\Utils\Json;
use Throwable;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;
use Tracy\Debugger;

/**
 * RFC 9457 error format: application/problem+json
 */
final readonly class ProblemDetailsErrorHandler implements ErrorHandlerInterface
{
    public const string TYPE_VALIDATION = 'validation-error';

    public const string TYPE_UNAUTHORIZED = 'unauthorized';

    public const string TYPE_NOT_FOUND = 'not-found';

    public const string TYPE_RATE_LIMITED = 'rate-limited';

    public const string TYPE_INTERNAL = 'internal-error';

    public const string TYPE_INVALID_OUTPUT = 'invalid-output';

    private ?string $typeBaseUri;

    /**
     * @param string|null $typeBaseUri base of problem type URIs, e.g. https://api.example.com/problems/
     *                                 a missing trailing slash is added,
     *                                 null or an empty string sends type "about:blank" for every problem
     */
    public function __construct(
        private ConfiguratorInterface $outputConfigurator,
        ?string $typeBaseUri = null,
    ) {
        $base = rtrim($typeBaseUri ?? '', '/');
        $this->typeBaseUri = $base === '' ? null : $base . '/';
    }

    #[\Override]
    public function handle(Throwable $exception, array $params): ProblemDetailsResponse
    {
        return $this->internalError($exception);
    }

    /**
     * Errors are always sent in extension "errors": [{"parameter": ..., "detail": ...}]
     */
    #[\Override]
    public function handleInputParams(array $errors): ProblemDetailsResponse
    {
        $list = [];
        foreach ($errors as $parameter => $messages) {
            foreach (is_array($messages) ? $messages : [$messages] as $message) {
                $list[] = ['parameter' => (string) $parameter, 'detail' => is_string($message) ? $message : Json::encode($message)];
            }
        }

        return $this->problem(Response::S400_BAD_REQUEST, 'Bad Request', self::TYPE_VALIDATION, 'Request parameters are not valid.', ['errors' => $list]);
    }

    /**
     * Validator errors are sent in extension "outputErrors" only when showErrorDetail() is on,
     * "errors" stays reserved for the 400 list of {"parameter": ..., "detail": ...}
     */
    #[\Override]
    public function handleSchema(array $errors, array $params): ProblemDetailsResponse
    {
        Debugger::log($errors, Debugger::ERROR);
        $extensions = $this->outputConfigurator->showErrorDetail() ? ['outputErrors' => $errors] : [];
        return $this->problem(Response::S500_INTERNAL_SERVER_ERROR, 'Internal Server Error', self::TYPE_INVALID_OUTPUT, null, $extensions);
    }

    #[\Override]
    public function handleAuthorization(ApiAuthorizationInterface $auth, array $params): ProblemDetailsResponse
    {
        return $this->problem(Response::S401_UNAUTHORIZED, 'Unauthorized', self::TYPE_UNAUTHORIZED, $auth->getErrorMessage());
    }

    #[\Override]
    public function handleAuthorizationException(Throwable $exception, array $params): ProblemDetailsResponse
    {
        return $this->internalError($exception);
    }

    #[\Override]
    public function handleNotFound(EndpointInterface $endpoint): ProblemDetailsResponse
    {
        return $this->problem(Response::S404_NOT_FOUND, 'Not Found', self::TYPE_NOT_FOUND, 'Unknown api endpoint');
    }

    #[\Override]
    public function handleRateLimit(RateLimitResponse $rateLimit): ProblemDetailsResponse
    {
        $retryAfter = $rateLimit->getRetryAfter();
        $extensions = ['limit' => $rateLimit->getLimit()];
        $detail = 'Too many requests.';
        if ($retryAfter !== null) {
            $extensions['retryAfter'] = $retryAfter;
            $detail = 'Too many requests. Retry after ' . $retryAfter . ' seconds.';
        }

        return $this->problem(Response::S429_TOO_MANY_REQUESTS, 'Too Many Requests', self::TYPE_RATE_LIMITED, $detail, $extensions);
    }

    private function internalError(Throwable $exception): ProblemDetailsResponse
    {
        Debugger::log($exception, Debugger::EXCEPTION);
        $detail = $this->outputConfigurator->showErrorDetail() ? $exception->getMessage() : null;
        return $this->problem(Response::S500_INTERNAL_SERVER_ERROR, 'Internal Server Error', self::TYPE_INTERNAL, $detail);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function problem(int $status, string $title, string $type, ?string $detail, array $extensions = []): ProblemDetailsResponse
    {
        $typeUri = $this->typeBaseUri === null ? ProblemDetails::DEFAULT_TYPE : $this->typeBaseUri . $type;
        return new ProblemDetailsResponse(new ProblemDetails($status, $title, $detail, $typeUri, null, $extensions));
    }
}
