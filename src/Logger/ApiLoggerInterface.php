<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Logger;

interface ApiLoggerInterface
{
    public function log(
        int $responseCode,
        string $requestMethod,
        string $requestHeader,
        string $requestUri,
        string $requestIp,
        string $requestAgent,
        int $responseTime
    ): bool;
}
