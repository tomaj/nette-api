<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Throwable;

interface ErrorHandlerInterface
{
    public function handle(Throwable $error): JsonApiResponse;

    public function handleInputParams(array $errors): JsonApiResponse;

    public function handleSchema(array $errors): JsonApiResponse;

    public function handleAuthorization(ApiAuthorizationInterface $auth): JsonApiResponse;

    public function handleAuthorizationException(Throwable $exception): JsonApiResponse;
}
