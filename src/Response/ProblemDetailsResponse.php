<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use Tomaj\NetteApi\Error\ProblemDetails;

/**
 * Error response with RFC 9457 body and Content-Type application/problem+json
 */
final class ProblemDetailsResponse extends JsonApiResponse
{
    public const string CONTENT_TYPE = 'application/problem+json';

    public function __construct(private readonly ProblemDetails $problem)
    {
        parent::__construct($problem->status, $problem, self::CONTENT_TYPE);
    }

    public function getProblem(): ProblemDetails
    {
        return $this->problem;
    }
}
