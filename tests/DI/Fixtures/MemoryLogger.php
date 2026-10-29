<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\Logger\ApiLoggerInterface;

final class MemoryLogger implements ApiLoggerInterface
{
    /** @var list<int> */
    public array $codes = [];

    #[\Override]
    public function log(int $responseCode, string $requestMethod, string $requestHeader, string $requestUri, string $requestIp, string $requestAgent, int $responseTime): bool
    {
        $this->codes[] = $responseCode;
        return true;
    }
}
