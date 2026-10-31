<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use Nette\Http\IRequest;

/**
 * The address of the client from Nette\Http\IRequest::getRemoteAddress(). Nette trusts the forwarded headers
 * (Forwarded, X-Forwarded-For) only from the proxies listed in http: proxy: of the Nette configuration,
 * so a client cannot forge its address. Without the request, the address of the TCP connection.
 */
final readonly class IpDetector implements IpDetectorInterface
{
    public function __construct(private ?IRequest $request = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getRequestIp(): string
    {
        $ip = $this->request?->getRemoteAddress() ?? ($_SERVER['REMOTE_ADDR'] ?? null);

        return is_string($ip) && $ip !== '' ? $ip : 'cli';
    }
}
