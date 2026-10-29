<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Throwable;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * Uses the problem details handler only when the request Accept header lists application/problem+json,
 * every other request gets the fallback (usually DefaultErrorHandler)
 *
 * Every error response gets "Vary: Accept" (added next to other Vary headers, e.g. Origin from CORS),
 * so caches keep the two formats apart
 */
final readonly class NegotiatingErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private ErrorHandlerInterface $fallback,
        private ErrorHandlerInterface $problemDetails,
        private IRequest $request,
        private IResponse $response,
    ) {
    }

    #[\Override]
    public function handle(Throwable $exception, array $params): ResponseInterface
    {
        return $this->select()->handle($exception, $params);
    }

    #[\Override]
    public function handleInputParams(array $errors): ResponseInterface
    {
        return $this->select()->handleInputParams($errors);
    }

    #[\Override]
    public function handleSchema(array $errors, array $params): ResponseInterface
    {
        return $this->select()->handleSchema($errors, $params);
    }

    #[\Override]
    public function handleAuthorization(ApiAuthorizationInterface $auth, array $params): ResponseInterface
    {
        return $this->select()->handleAuthorization($auth, $params);
    }

    #[\Override]
    public function handleAuthorizationException(Throwable $exception, array $params): ResponseInterface
    {
        return $this->select()->handleAuthorizationException($exception, $params);
    }

    #[\Override]
    public function handleNotFound(EndpointInterface $endpoint): ResponseInterface
    {
        return $this->select()->handleNotFound($endpoint);
    }

    #[\Override]
    public function handleRateLimit(RateLimitResponse $rateLimit): ResponseInterface
    {
        return $this->select()->handleRateLimit($rateLimit);
    }

    private function select(): ErrorHandlerInterface
    {
        $this->response->addHeader('Vary', 'Accept');
        $accept = strtolower($this->request->getHeader('Accept') ?? '');
        foreach (explode(',', $accept) as $range) {
            $parts = array_map(trim(...), explode(';', $range));
            if ($parts[0] !== ProblemDetailsResponse::CONTENT_TYPE) {
                continue;
            }

            if (!$this->refused($parts)) {
                return $this->problemDetails;
            }
        }

        return $this->fallback;
    }

    /**
     * q=0 (also q=0.0, q=0.000) means "not acceptable"
     *
     * @param list<string> $params media range and its parameters
     */
    private function refused(array $params): bool
    {
        return array_any($params, fn(string $param): bool => preg_match('/^q\s*=\s*0(\.0{0,3})?$/', $param) === 1);
    }
}
