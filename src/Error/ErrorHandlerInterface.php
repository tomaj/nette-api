<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Throwable;

interface ErrorHandlerInterface
{
    /**
     * @param array<mixed> $params
     */
    public function handle(Throwable $exception, array $params): JsonApiResponse;

    /**
     * @param array<string> $errors
     * @param array<mixed> $params
     */
    public function handleInputParams(array $errors): JsonApiResponse;

    /**
     * @param array<string> $errors
     * @param array<mixed> $params
     */
    public function handleSchema(array $errors, array $params): JsonApiResponse;

    /**
     * @param array<mixed> $params
     */
    public function handleAuthorization(ApiAuthorizationInterface $auth, array $params): JsonApiResponse;

    /**
     * @param array<mixed> $params
     */
    public function handleAuthorizationException(Throwable $exception, array $params): JsonApiResponse;
}
