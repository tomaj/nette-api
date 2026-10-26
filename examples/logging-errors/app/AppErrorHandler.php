<?php

declare(strict_types=1);

namespace App;

use Throwable;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\Response\JsonApiResponse;

/**
 * Answers MaintenanceException with 503 and leaves everything else to DefaultErrorHandler.
 */
final class AppErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private ErrorHandlerInterface $inner)
    {
    }

    public function handle(Throwable $exception, array $params): JsonApiResponse
    {
        if ($exception instanceof MaintenanceException) {
            return new JsonApiResponse(503, ['status' => 'error', 'message' => 'Down for maintenance, try again later']);
        }
        return $this->inner->handle($exception, $params);
    }

    public function handleInputParams(array $errors): JsonApiResponse
    {
        return $this->inner->handleInputParams($errors);
    }

    public function handleSchema(array $errors, array $params): JsonApiResponse
    {
        return $this->inner->handleSchema($errors, $params);
    }

    public function handleAuthorization(ApiAuthorizationInterface $auth, array $params): JsonApiResponse
    {
        return $this->inner->handleAuthorization($auth, $params);
    }

    public function handleAuthorizationException(Throwable $exception, array $params): JsonApiResponse
    {
        return $this->inner->handleAuthorizationException($exception, $params);
    }
}
