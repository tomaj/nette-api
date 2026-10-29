<?php

declare(strict_types=1);

namespace App;

use Throwable;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * Answers MaintenanceException with 503 and leaves everything else to DefaultErrorHandler.
 */
final class AppErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private ErrorHandlerInterface $inner)
    {
    }

    public function handle(Throwable $exception, array $params): ResponseInterface
    {
        if ($exception instanceof MaintenanceException) {
            return new JsonApiResponse(503, ['status' => 'error', 'message' => 'Down for maintenance, try again later']);
        }
        return $this->inner->handle($exception, $params);
    }

    public function handleInputParams(array $errors): ResponseInterface
    {
        return $this->inner->handleInputParams($errors);
    }

    public function handleSchema(array $errors, array $params): ResponseInterface
    {
        return $this->inner->handleSchema($errors, $params);
    }

    public function handleAuthorization(ApiAuthorizationInterface $auth, array $params): ResponseInterface
    {
        return $this->inner->handleAuthorization($auth, $params);
    }

    public function handleAuthorizationException(Throwable $exception, array $params): ResponseInterface
    {
        return $this->inner->handleAuthorizationException($exception, $params);
    }

    public function handleNotFound(EndpointInterface $endpoint): ResponseInterface
    {
        return $this->inner->handleNotFound($endpoint);
    }

    public function handleRateLimit(RateLimitResponse $rateLimit): ResponseInterface
    {
        return $this->inner->handleRateLimit($rateLimit);
    }
}
