<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Nette\Http\Response;
use Throwable;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tracy\Debugger;

/**
 * Legacy error format: {"status": "error", "message": ...}
 */
final readonly class DefaultErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private ConfiguratorInterface $outputConfigurator)
    {
    }

    #[\Override]
    public function handle(Throwable $exception, array $params): JsonApiResponse
    {
        return $this->internalError($exception);
    }

    #[\Override]
    public function handleInputParams(array $errors): JsonApiResponse
    {
        return $this->error(Response::S400_BAD_REQUEST, 'wrong input', $errors);
    }

    #[\Override]
    public function handleSchema(array $errors, array $params): JsonApiResponse
    {
        Debugger::log($errors, Debugger::ERROR);
        return $this->error(Response::S500_INTERNAL_SERVER_ERROR, 'Internal server error', $errors);
    }

    #[\Override]
    public function handleAuthorization(ApiAuthorizationInterface $auth, array $params): JsonApiResponse
    {
        return new JsonApiResponse(Response::S401_UNAUTHORIZED, ['status' => 'error', 'message' => $auth->getErrorMessage()]);
    }

    #[\Override]
    public function handleAuthorizationException(Throwable $exception, array $params): JsonApiResponse
    {
        return $this->internalError($exception);
    }

    #[\Override]
    public function handleNotFound(EndpointInterface $endpoint): JsonApiResponse
    {
        return new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Unknown api endpoint']);
    }

    #[\Override]
    public function handleRateLimit(RateLimitResponse $rateLimit): JsonApiResponse
    {
        $retryAfter = $rateLimit->getRetryAfter();
        $message = $retryAfter === null ? 'Too many requests.' : 'Too many requests. Retry after ' . $retryAfter . ' seconds.';
        return new JsonApiResponse(Response::S429_TOO_MANY_REQUESTS, ['status' => 'error', 'message' => $message]);
    }

    private function internalError(Throwable $exception): JsonApiResponse
    {
        Debugger::log($exception, Debugger::EXCEPTION);
        return $this->error(Response::S500_INTERNAL_SERVER_ERROR, 'Internal server error', $exception->getMessage());
    }

    /**
     * @param array<mixed>|string $detail sent only when showErrorDetail() is on
     */
    private function error(int $code, string $message, array|string $detail): JsonApiResponse
    {
        $payload = ['status' => 'error', 'message' => $message];
        if ($this->outputConfigurator->showErrorDetail()) {
            $payload['detail'] = $detail;
        }

        return new JsonApiResponse($code, $payload);
    }
}
