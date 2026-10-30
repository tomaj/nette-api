<?php

declare(strict_types=1);

namespace App;

use Tomaj\NetteApi\Logger\ApiLoggerInterface;

/**
 * Writes one JSON line per API request.
 * The headers arrive as "Name: value" lines; secret header and query parameter values are already *** because the presenter masks them.
 */
final class FileApiLogger implements ApiLoggerInterface
{
    public function __construct(private string $file)
    {
    }

    public function log(
        int $responseCode,
        string $requestMethod,
        string $requestHeader,
        string $requestUri,
        string $requestIp,
        string $requestAgent,
        int $responseTime
    ): bool {
        $headers = [];
        foreach (explode(PHP_EOL, trim($requestHeader)) as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            $headers[$name] = $value;
        }

        $line = json_encode([
            'code' => $responseCode,
            'method' => $requestMethod,
            'uri' => $requestUri,
            'ip' => $requestIp,
            'agent' => $requestAgent,
            'time_ms' => $responseTime,
            'headers' => $headers,
        ], JSON_UNESCAPED_SLASHES);

        return file_put_contents($this->file, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    }
}
