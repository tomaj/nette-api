<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Nette\Application\Request;
use Throwable;

interface ErrorHandlerInterface
{
    public function handle(Throwable $error, Request $request): JsonApiResponse;

    public function handleInputParams(array $errors, Request $request): JsonApiResponse;

    public function handleSchema(array $errors, Request $request): JsonApiResponse;

    public function handleAuthorization(ApiAuthorizationInterface $auth, Request $request): JsonApiResponse;

    public function handleAuthorizationException(Throwable $exception, Request $request): JsonApiResponse;
}
