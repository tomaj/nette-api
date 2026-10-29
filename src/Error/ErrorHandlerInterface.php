<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Throwable;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * Builds every error response ApiPresenter sends
 */
interface ErrorHandlerInterface
{
    /**
     * Uncaught exception from a handler (500)
     *
     * @param array<mixed> $params
     */
    public function handle(Throwable $exception, array $params): ResponseInterface;

    /**
     * Invalid input params (400)
     *
     * @param array<mixed> $errors errors per param key
     */
    public function handleInputParams(array $errors): ResponseInterface;

    /**
     * Handler response does not match any of its outputs (500)
     *
     * @param array<mixed> $errors
     * @param array<mixed> $params
     */
    public function handleSchema(array $errors, array $params): ResponseInterface;

    /**
     * Authorization refused the request (401)
     *
     * @param array<mixed> $params
     */
    public function handleAuthorization(ApiAuthorizationInterface $auth, array $params): ResponseInterface;

    /**
     * Authorization threw an exception (500)
     *
     * @param array<mixed> $params
     */
    public function handleAuthorizationException(Throwable $exception, array $params): ResponseInterface;

    /**
     * No api is registered for the requested endpoint (404)
     */
    public function handleNotFound(EndpointInterface $endpoint): ResponseInterface;

    /**
     * Rate limit has no remaining requests and returned no own error response (429)
     */
    public function handleRateLimit(RateLimitResponse $rateLimit): ResponseInterface;
}
