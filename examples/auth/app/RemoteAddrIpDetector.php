<?php

declare(strict_types=1);

namespace App;

use Tomaj\NetteApi\Misc\IpDetectorInterface;

/**
 * Uses only the address of the TCP connection.
 *
 * The bundled Tomaj\NetteApi\Misc\IpDetector prefers the Client-IP and X-Forwarded-For
 * headers, which any client can send. Use it only behind a proxy that overwrites them.
 */
final class RemoteAddrIpDetector implements IpDetectorInterface
{
    public function getRequestIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'cli';
    }
}
